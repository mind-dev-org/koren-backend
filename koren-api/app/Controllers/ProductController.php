<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\Category;
use Vine\Core\Request;
use Vine\Core\Response;

class ProductController
{
    public function index(Request $request): Response
    {
        $query = Product::query()
            ->select([
                'p.*',
                'u.name as farmer_name',
                'u.avatar_url as farmer_avatar',
                'fp.region as farmer_region',
                'c.slug as category_slug',
                'c.name as category_name',
            ])
            ->join('users u', 'p.farmer_id = u.id')
            ->join('farmer_profiles fp', 'fp.user_id = u.id')
            ->join('categories c', 'p.category_id = c.id')
            ->where('p.is_active', '=', true)
            ->where('p.stock_qty', '>', 0);

        $query->when($request->query('category'), fn($q) =>
            $q->where('c.slug', '=', $request->query('category'))
        );

        $query->when($request->query('farmer_id'), fn($q) =>
            $q->where('p.farmer_id', '=', (int) $request->query('farmer_id'))
        );

        $query->when($request->query('price_tier'), function($q) use ($request) {
            $tier = $request->query('price_tier');
            if ($tier === 'low')  $q->where('p.price', '<', 2);
            if ($tier === 'mid')  { $q->where('p.price', '>=', 2)->where('p.price', '<=', 5); }
            if ($tier === 'high') $q->where('p.price', '>', 5);
        });

        $query->when($request->query('price_min'), fn($q) =>
            $q->where('p.price', '>=', (float) $request->query('price_min'))
        );

        $query->when($request->query('price_max'), fn($q) =>
            $q->where('p.price', '<=', (float) $request->query('price_max'))
        );

        $query->when($request->query('in_stock') === 'true', fn($q) =>
            $q->where('p.stock_qty', '>', 0)
        );

        $query->when($request->query('tags'), function($q) use ($request) {
            foreach (explode(',', $request->query('tags')) as $tag) {
                $q->where('p.tags::text', 'ILIKE', '%' . trim($tag) . '%');
            }
        });

        $query->when($request->query('search'), fn($q) =>
            $q->where('p.name', 'ILIKE', '%' . $request->query('search') . '%')
        );

        $sort = $request->query('sort', 'newest');
        match($sort) {
            'price_asc'  => $query->orderBy('p.price', 'ASC'),
            'price_desc' => $query->orderBy('p.price', 'DESC'),
            'name_asc'   => $query->orderBy('p.name', 'ASC'),
            default      => $query->orderBy('p.created_at', 'DESC'),
        };

        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(24, max(1, (int) $request->query('per_page', 6)));
        $result  = $query->paginate($page, $perPage);

        $result['data'] = array_map([$this, 'formatProduct'], $result['data']);

        $filtersApplied = array_filter([
            'category'   => $request->query('category'),
            'farmer_id'  => $request->query('farmer_id'),
            'price_tier' => $request->query('price_tier'),
            'price_min'  => $request->query('price_min'),
            'price_max'  => $request->query('price_max'),
            'tags'       => $request->query('tags'),
            'sort'       => $sort !== 'newest' ? $sort : null,
            'search'     => $request->query('search'),
        ]);

        $result['meta']['generated_at']    = date('c');
        $result['meta']['filters_applied'] = (object) $filtersApplied;

        return Response::collection($result['data'], $result['meta']);
    }

    public function featured(Request $request): Response
    {
        $product = Product::query()
            ->select(['p.*', 'u.name as farmer_name', 'fp.region as farmer_region', 'fp.farm_types'])
            ->join('users u', 'p.farmer_id = u.id')
            ->join('farmer_profiles fp', 'fp.user_id = u.id')
            ->where('p.is_featured', '=', true)
            ->where('p.is_active', '=', true)
            ->first();

        if (!$product) {
            return Response::notFound('No featured product found');
        }

        return Response::success(array_merge($this->formatProduct($product), [
            'badge' => "THIS WEEK'S TOP PICK",
        ]));
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->params['id'];

        $product = Product::query()
            ->select(['p.*', 'u.name as farmer_name', 'u.id as farmer_id', 'fp.region as farmer_region', 'c.slug as category_slug', 'c.name as category_name'])
            ->join('users u', 'p.farmer_id = u.id')
            ->join('farmer_profiles fp', 'fp.user_id = u.id')
            ->join('categories c', 'p.category_id = c.id')
            ->where('p.id', '=', $id)
            ->first();

        if (!$product) {
            return Response::notFound('Product not found');
        }

        return Response::success($this->formatProduct($product));
    }

    public function categories(Request $request): Response
    {
        return Response::success(Category::all());
    }

    private function formatProduct(array $p): array
    {
        return [
            'id'                        => (int) $p['id'],
            'name'                      => $p['name'],
            'slug'                      => $p['slug'],
            'description'               => $p['description'],
            'price'                     => (float) $p['price'],
            'unit'                      => $p['unit'],
            'stock_qty'                 => (int) $p['stock_qty'],
            'image_url'                 => $p['image_url'],
            'tags'                      => $this->parsePgArray($p['tags'] ?? '{}'),
            'is_featured'               => (bool) $p['is_featured'],
            'harvested_at'              => $p['harvested_at'],
            'available_in_auto_delivery'=> (bool) ($p['is_active'] ?? true),
            'farmer'                    => [
                'id'         => (int) ($p['farmer_id'] ?? 0),
                'name'       => $p['farmer_name'] ?? null,
                'region'     => $p['farmer_region'] ?? null,
                'avatar_url' => $p['farmer_avatar'] ?? null,
            ],
            'category'                  => [
                'slug' => $p['category_slug'] ?? null,
                'name' => $p['category_name'] ?? null,
            ],
        ];
    }

    private function parsePgArray(string $pgArray): array
    {
        $cleaned = trim($pgArray, '{}');
        if (empty($cleaned)) return [];
        return explode(',', $cleaned);
    }
}
