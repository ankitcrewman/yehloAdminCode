<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Order;
use App\Models\Review;
use App\Models\Category;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Brand;
use App\Models\Module;



class ItemController extends Controller
{

    public function get_latest_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'category_id' => 'required',
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id') ?? null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');

        $items = ProductLogic::get_latest_products($zone_id, $request['limit'], $request['offset'], $request['store_id'], $request['category_id'], $type, $min, $max, $product_id);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_new_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id') ?? null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $limit = isset($request['limit']) ? $request['limit'] : 50;
        $offset = isset($request['offset']) ? $request['offset'] : 1;

        $items = ProductLogic::get_new_products($zone_id, $type, $min, $max, $product_id, $limit, $offset);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_searched_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone_id = $request->header('zoneId');

        $key = explode(' ', $request['name']);

        $limit = $request['limit'] ?? 10;
        $offset = $request['offset'] ?? 1;
        $category_ids = $request['category_ids'] ? json_decode($request['category_ids']) : '';
        $brand_id = $request->brand_id;

        $type = $request->query('type', 'all');

        $items = Item::active()->type($type)
            ->with('store', function ($query) {
                $query->withCount(['campaigns' => function ($query) {
                    $query->Running();
                }]);
            })
            ->when($request->category_id, function ($query) use ($request) {
                $query->whereHas('category', function ($q) use ($request) {
                    return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
                });
            })
            ->when($category_ids, function ($query) use ($category_ids) {
                $query->whereHas('category', function ($q) use ($category_ids) {
                    return $q->whereIn('id', $category_ids)->orWhereIn('parent_id', $category_ids);
                });
            })
            ->when($request->store_id, function ($query) use ($request) {
                return $query->where('store_id', $request->store_id);
            })
            ->when($brand_id, function ($query) use ($request) {
                return $query->where('brand_id', $request->brand_id);
            })
            ->whereHas('module.zones', function ($query) use ($zone_id) {
                $query->whereIn('zones.id', json_decode($zone_id, true));
            })
            ->whereHas('store', function ($query) use ($zone_id) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })->whereIn('zone_id', json_decode($zone_id, true));
            })
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
                $q->orWhereHas('translations', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('value', 'like', "%{$value}%");
                        };
                    });
                });
                $q->orWhereHas('tags', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('tag', 'like', "%{$value}%");
                        };
                    });
                });
                $q->orWhereHas('category.parent', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('name', 'like', "%{$value}%");
                        };
                    });
                });
                $q->orWhereHas('category', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('name', 'like', "%{$value}%");
                        };
                    });
                });
            })
            ->orderByRaw("FIELD(name, ?) DESC", [$request['name']])
            ->paginate($limit, ['*'], 'page', $offset);

        $data =  [
            'total_size' => $items->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $items->items()
        ];

        $data['products'] = Helpers::product_data_formatting($data['products'], true, false, app()->getLocale());
        return response()->json($data, 200);
    }




    public function get_searched_products_suggestion(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone_id = $request->header('zoneId');

        $key = explode(' ', $request['name']);

        $limit = $request['limit'] ?? 10;
        $offset = $request['offset'] ?? 1;

        $type = $request->query('type', 'all');

        $items = Item::active()->type($type)

            ->when($request->category_id, function ($query) use ($request) {
                $query->whereHas('category', function ($q) use ($request) {
                    return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
                });
            })
            ->when($request->store_id, function ($query) use ($request) {
                return $query->where('store_id', $request->store_id);
            })
            ->whereHas('module.zones', function ($query) use ($zone_id) {
                $query->whereIn('zones.id', json_decode($zone_id, true));
            })
            ->whereHas('store', function ($query) use ($zone_id) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })->whereIn('zone_id', json_decode($zone_id, true));
            })
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
                $q->orWhereHas('translations', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('value', 'like', "%{$value}%");
                        };
                    });
                });
                $q->orWhereHas('tags', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('tag', 'like', "%{$value}%");
                        };
                    });
                });
            })->select(['name', 'image'])

            ->paginate($limit, ['*'], 'page', $offset);

        $data =  [
            'total_size' => $items->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $items->items()
        ];

        return response()->json($data, 200);
    }







    // public function get_combined_data(Request $request)
    // {
    //     // Validate incoming request parameters
    //     $validator = Validator::make($request->all(), [
    //         'name' => 'sometimes|string',
    //         'data_type' => 'required|string|in:brand,searched,category,subcategory,new,discounted',
    //         'list_type' => 'required|string|in:store,product,item',
    //         'limit' => 'sometimes|integer',
    //         'offset' => 'sometimes|integer',
    //         // 'category_ids' => 'sometimes|array',
    //         // 'brand_ids' => 'sometimes|array',
    //         // 'filter' => 'sometimes|array',
    //         'rating_count' => 'sometimes|integer',
    //         'min_price' => 'sometimes|numeric',
    //         'max_price' => 'sometimes|numeric',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 403);
    //     }


    //     $zone_id = $request->header('zoneId');
    //     $module_id = $request->header('moduleId');
    //     $name = $request->input('name');
    //     $data_type = $request->input('data_type');
    //     $list_type = $request->input('list_type');
    //     $limit = $request->input('limit', 12);
    //     $offset = $request->input('offset', 1);
    //     $category_ids = $request->input('category_ids', []);
    //     $brand_ids = $request->input('brand_ids', []);
    //     $filter = $request->input('filter', []);
    //     $rating_count = $request->input('rating_count', 0);
    //     $min_price = $request->input('min_price', 0);
    //     $max_price = $request->input('max_price', 20000);

    //     // Initialize item query
    //     $itemQuery = Item::query();

    //     // Apply common filters
    //     if (!empty($category_ids)) {
    //         $itemQuery->where(function ($query) use ($category_ids) {
    //             $query->whereIn('category_id', $category_ids)
    //                 ->orWhereJsonContains('category_ids', $category_ids);
    //         });
    //     }

    //     if (!empty($brand_ids)) {
    //         $itemQuery->whereIn('brand_id', $brand_ids);
    //     }

    //     if (!empty($filter)) {
    //         foreach ($filter as $key => $value) {
    //             $itemQuery->where($key, $value);
    //         }
    //     }

    //     if ($min_price !== null && $max_price !== null) {
    //         $itemQuery->whereBetween('price', [$min_price, $max_price]);
    //     }

    //     if ($rating_count > 0) {
    //         $itemQuery->where('rating_count', '>=', $rating_count);
    //     }

    //     // Apply zone and module filters
    //     $itemQuery->whereHas('module.zones', function ($q) use ($zone_id) {
    //         $q->whereIn('zones.id', json_decode($zone_id, true));
    //     });

    //     if ($module_id) {
    //         $itemQuery->where('module_id', $module_id);
    //     }

    //     switch ($data_type) {
    //         case 'store':
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             dd($results);
    //             return response()->json([
    //                 'total_size' => $results->total(),
    //                 'limit' => $limit,
    //                 'offset' => $offset,
    //                 'data' => $results->items()
    //             ], 200);


    //         case 'brand':

    //             if ($name) {
    //                 $brand_ids = Brand::where('name', 'like', "%{$name}%")->pluck('id')->toArray();
    //                 $itemQuery->whereIn('brand_id', $brand_ids);
    //             }
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             return response()->json([
    //                 'total_size' => $results->total(),
    //                 'limit' => $limit,
    //                 'offset' => $offset,
    //                 'data' => $results->items()
    //             ], 200);

    //         case 'searched':

    //             if ($name) {
    //                 $key = explode(' ', $name);
    //                 $itemQuery->where(function ($q) use ($key) {
    //                     foreach ($key as $value) {
    //                         $q->orWhere('name', 'like', "%{$value}%");
    //                     }
    //                     $q->orWhereHas('tags', function ($query) use ($key) {
    //                         $query->where(function ($q) use ($key) {
    //                             foreach ($key as $value) {
    //                                 $q->where('tag', 'like', "%{$value}%");
    //                             }
    //                         });
    //                     });
    //                     $q->orWhereHas('category.parent', function ($query) use ($key) {
    //                         $query->where(function ($q) use ($key) {
    //                             foreach ($key as $value) {
    //                                 $q->where('name', 'like', "%{$value}%");
    //                             }
    //                         });
    //                     });
    //                     $q->orWhereHas('category', function ($query) use ($key) {
    //                         $query->where(function ($q) use ($key) {
    //                             foreach ($key as $value) {
    //                                 $q->where('name', 'like', "%{$value}%");
    //                             }
    //                         });
    //                     });
    //                 });
    //                 $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //                 return response()->json([
    //                     'total_size' => $results->total(),
    //                     'limit' => $limit,
    //                     'offset' => $offset,
    //                     'data' => $results->items()
    //                 ], 200);
    //             }
    //             break;

    //         case 'category':
    //             // Category-specific logic
    //             if (!empty($category_ids)) {
    //                 $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //                 return response()->json([
    //                     'total_size' => $results->total(),
    //                     'limit' => $limit,
    //                     'offset' => $offset,
    //                     'data' => $results->items()
    //                 ], 200);
    //             } else {
    //                 $categories = Category::where('parent_id', 0)->get();
    //                 return response()->json([
    //                     'total_size' => $categories->count(),
    //                     'limit' => $limit,
    //                     'offset' => $offset,
    //                     'data' => $categories
    //                 ], 200);
    //             }
    //             break;

    //         case 'subcategory':
    //             // Subcategory-specific logic
    //             if (!empty($category_ids)) {
    //                 $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //                 return response()->json([
    //                     'total_size' => $results->total(),
    //                     'limit' => $limit,
    //                     'offset' => $offset,
    //                     'data' => $results->items()
    //                 ], 200);
    //             }
    //             break;

    //         case 'new':
    //             // New/latest-specific logic
    //             $itemQuery->orderBy('created_at', 'desc');
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             return response()->json([
    //                 'total_size' => $results->total(),
    //                 'limit' => $limit,
    //                 'offset' => $offset,
    //                 'data' => $results->items()
    //             ], 200);
    //             break;

    //         case 'discounted':
    //             // Discounted-specific logic
    //             $itemQuery->where('discount', '>', 0);
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             return response()->json([
    //                 'total_size' => $results->total(),
    //                 'limit' => $limit,
    //                 'offset' => $offset,
    //                 'data' => $results->items()
    //             ], 200);
    //             break;

    //         default:
    //             // Handle additional filters if needed
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             return response()->json([
    //                 'total_size' => $results->total(),
    //                 'limit' => $limit,
    //                 'offset' => $offset,
    //                 'data' => $results->items()
    //             ], 200);
    //             break;
    //     }

    //     // Return response with data
    //     return response()->json([
    //         'total_size' => 0,
    //         'limit' => $limit,
    //         'offset' => $offset,
    //         'data' => []
    //     ], 200);
    // }

    // public function get_combined_data(Request $request)
    // {
    //     // Validate incoming request parameters
    //     $validator = Validator::make($request->all(), [
    //         'name' => 'sometimes|string',
    //         'data_type' => 'required|string|in:brand,searched,category,subcategory,new,discounted',
    //         'list_type' => 'required|string|in:store,product,item',
    //         'limit' => 'sometimes|integer',
    //         'offset' => 'sometimes|integer',
    //         'rating_count' => 'sometimes|integer',
    //         'min_price' => 'sometimes|numeric',
    //         'max_price' => 'sometimes|numeric',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 403);
    //     }

    //     $zone_id = $request->header('zoneId');
    //     $module_id = $request->header('moduleId');
    //     $name = $request->input('name');
    //     $data_type = $request->input('data_type');
    //     $list_type = $request->input('list_type');
    //     $limit = $request->input('limit', 12);
    //     $offset = $request->input('offset', 1);
    //     $category_ids = $request->input('category_ids', []);
    //     $brand_ids = $request->input('brand_ids', []);
    //     $filter = $request->input('filter', []);
    //     $rating_count = $request->input('rating_count', 0);
    //     $min_price = $request->input('min_price', 0);
    //     $max_price = $request->input('max_price', 20000);

    //     // Initialize item query
    //     $itemQuery = Item::query();

    //     // Apply common filters
    //     if (!empty($category_ids)) {
    //         $itemQuery->where(function ($query) use ($category_ids) {
    //             $query->whereIn('category_id', $category_ids)
    //                 ->orWhereJsonContains('category_ids', $category_ids);
    //         });
    //     }

    //     if (!empty($brand_ids)) {
    //         $itemQuery->whereIn('brand_id', $brand_ids);
    //     }

    //     if (!empty($filter)) {
    //         foreach ($filter as $key => $value) {
    //             $itemQuery->where($key, $value);
    //         }
    //     }

    //     if ($min_price !== null && $max_price !== null) {
    //         $itemQuery->whereBetween('price', [$min_price, $max_price]);
    //     }

    //     if ($rating_count > 0) {
    //         $itemQuery->where('rating_count', '>=', $rating_count);
    //     }

    //     // Apply zone and module filters
    //     $itemQuery->whereHas('module.zones', function ($q) use ($zone_id) {
    //         $q->whereIn('zones.id', json_decode($zone_id, true));
    //     });

    //     if ($module_id) {
    //         $itemQuery->where('module_id', $module_id);
    //     }

    //     // Fetch the module data
    //     $moduleData = Module::find($module_id);

    //     // Handle different data types
    //     switch ($data_type) {
    //         case 'store':
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             break;

    //         case 'brand':
    //             if ($name) {
    //                 $brand_ids = Brand::where('name', 'like', "%{$name}%")->pluck('id')->toArray();
    //                 $itemQuery->whereIn('brand_id', $brand_ids);
    //             }
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             break;

    //         case 'searched':
    //             if ($name) {
    //                 $key = explode(' ', $name);
    //                 $itemQuery->where(function ($q) use ($key) {
    //                     foreach ($key as $value) {
    //                         $q->orWhere('name', 'like', "%{$value}%");
    //                     }
    //                     $q->orWhereHas('tags', function ($query) use ($key) {
    //                         $query->where(function ($q) use ($key) {
    //                             foreach ($key as $value) {
    //                                 $q->where('tag', 'like', "%{$value}%");
    //                             }
    //                         });
    //                     });
    //                     $q->orWhereHas('category.parent', function ($query) use ($key) {
    //                         $query->where(function ($q) use ($key) {
    //                             foreach ($key as $value) {
    //                                 $q->where('name', 'like', "%{$value}%");
    //                             }
    //                         });
    //                     });
    //                     $q->orWhereHas('category', function ($query) use ($key) {
    //                         $query->where(function ($q) use ($key) {
    //                             foreach ($key as $value) {
    //                                 $q->where('name', 'like', "%{$value}%");
    //                             }
    //                         });
    //                     });

    //                 });
    //             }
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             break;

    //         case 'category':
    //             if (!empty($category_ids)) {
    //                 $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             } else {
    //                 $categories = Category::where('parent_id', 0)->get();
    //                 return response()->json([
    //                     'total_size' => $categories->count(),
    //                     'limit' => $limit,
    //                     'offset' => $offset,
    //                     'products' => $categories
    //                 ], 200);
    //             }
    //             break;

    //         case 'subcategory':
    //             if (!empty($category_ids)) {
    //                 $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             }
    //             break;

    //         case 'new':
    //             $itemQuery->orderBy('created_at', 'desc');
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             break;

    //         case 'discounted':
    //             $itemQuery->where('discount', '>', 0);
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             break;

    //         default:
    //             $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
    //             break;
    //     }

    //     // Add module data to each item in the results
    //     $itemsWithModuleData = $results->map(function ($item) use ($moduleData) {
    //         $item->module = $moduleData;
    //         return $item;
    //     });

    //     // Return response with data
    //     return response()->json([
    //         'total_size' => $results->total(),
    //         'limit' => $limit,
    //         'offset' => $offset,
    //         'products' => $itemsWithModuleData
    //     ], 200);
    // }

    public function get_combined_data(Request $request)
    {
        // Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'data_type' => 'required|string|in:brand,searched,category,subcategory,new,discounted',
            'list_type' => 'required|string|in:store,product,item',
            'limit' => 'sometimes|integer',
            'offset' => 'sometimes|integer',
            'rating_count' => 'sometimes|integer',
            'min_price' => 'sometimes|numeric',
            'max_price' => 'sometimes|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 403);
        }

        $zone_id = $request->header('zoneId');
        $module_id = $request->header('moduleId');
        $name = $request->input('name');
        $data_type = $request->input('data_type');
        $list_type = $request->input('list_type');
        $limit = $request->input('limit', 12);
        $offset = $request->input('offset', 1);
        $category_ids = $request->input('category_ids', []);
        $brand_ids = $request->input('brand_ids', []);
        $filter = $request->input('filter', []);
        $rating_count = $request->input('rating_count', 0);
        $min_price = $request->input('min_price', 0);
        $max_price = $request->input('max_price', 20000);

        // Initialize item query
        $itemQuery = Item::query();

        // Apply common filters
        if (!empty($category_ids)) {
            $itemQuery->where(function ($query) use ($category_ids) {
                $query->whereIn('category_id', $category_ids)
                    ->orWhereJsonContains('category_ids', $category_ids);
            });
        }

        if (!empty($brand_ids)) {
            $itemQuery->whereIn('brand_id', $brand_ids);
        }

        if (!empty($filter)) {
            foreach ($filter as $key => $value) {
                $itemQuery->where($key, $value);
            }
        }

        if ($min_price !== null && $max_price !== null) {
            $itemQuery->whereBetween('price', [$min_price, $max_price]);
        }

        if ($rating_count > 0) {
            $itemQuery->where('rating_count', '>=', $rating_count);
        }

        // Apply zone and module filters
        $itemQuery->whereHas('module.zones', function ($q) use ($zone_id) {
            $q->whereIn('zones.id', json_decode($zone_id, true));
        });

        if ($module_id) {
            $itemQuery->where('module_id', $module_id);
        }

        // Fetch the module data
        $moduleData = Module::find($module_id);

        // dd($moduleData->module_type);

        // Handle different data types
        switch ($data_type) {
            case 'store':
                $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
                break;

            case 'brand':
                if ($name) {
                    $brand_ids = Brand::where('name', 'like', "%{$name}%")->pluck('id')->toArray();
                    $itemQuery->whereIn('brand_id', $brand_ids);
                }
                $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
                break;

            case 'searched':
                if ($name) {
                    $key = explode(' ', $name);
                    $itemQuery->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('name', 'like', "%{$value}%");
                        }
                        $q->orWhereHas('tags', function ($query) use ($key) {
                            $query->where(function ($q) use ($key) {
                                foreach ($key as $value) {
                                    $q->where('tag', 'like', "%{$value}%");
                                }
                            });
                        });
                        $q->orWhereHas('category.parent', function ($query) use ($key) {
                            $query->where(function ($q) use ($key) {
                                foreach ($key as $value) {
                                    $q->where('name', 'like', "%{$value}%");
                                }
                            });
                        });
                        $q->orWhereHas('category', function ($query) use ($key) {
                            $query->where(function ($q) use ($key) {
                                foreach ($key as $value) {
                                    $q->where('name', 'like', "%{$value}%");
                                }
                            });
                        });
                    });
                }
                $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
                break;

            case 'category':
                if (!empty($category_ids)) {
                    $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
                } else {
                    $categories = Category::where('parent_id', 0)->get();
                    return response()->json([
                        'total_size' => $categories->count(),
                        'limit' => $limit,
                        'offset' => $offset,
                        'data' => $categories
                    ], 200);
                }
                break;

            case 'subcategory':
                if (!empty($category_ids)) {
                    $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
                }
                break;

            case 'new':
                $itemQuery->orderBy('created_at', 'desc');
                $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
                break;

            case 'discounted':
                $itemQuery->where('discount', '>', 0);
                $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
                break;

            default:
                $results = $itemQuery->paginate($limit, ['*'], 'page', $offset);
                break;
        }

        // Add module data to each item in the results
        $itemsWithModuleData = $results->map(function ($item) use ($moduleData) {
            $item->module = $moduleData;

            $item->module_type = $moduleData->module_type;
            // Transform category_ids to the desired format
            if ($item->category_ids) {
                $categoryIds = json_decode($item->category_ids, true);
                $formattedCategoryIds = [];

                foreach ($categoryIds as $category) {
                    $categoryData = Category::find($category['id']);
                    $formattedCategoryIds[] = [
                        'id' => $category['id'],
                        'position' => $category['position'],
                        'name' => $categoryData ? $categoryData->name : null
                    ];
                }

                $item->category_ids = $formattedCategoryIds;
            }

            // Transform variations to JSON object
            if ($item->variations) {
                $item->variations = json_decode($item->variations, true);
            }

            // Transform attributes to JSON array
            if ($item->attributes) {
                $item->attributes = json_decode($item->attributes, true);
            }

            // Transform choice_options to JSON array
            if ($item->choice_options) {
                $item->choice_options = json_decode($item->choice_options, true);
            }

            return $item;
        });

        // Return response with data
        return response()->json([
            'total_size' => $results->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $itemsWithModuleData
        ], 200);
    }






    public function get_popular_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');

        $zone_id = $request->header('zoneId');
        $items = ProductLogic::popular_products($zone_id, $request['limit'], $request['offset'], $type);
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_most_reviewed_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');

        $zone_id = $request->header('zoneId');
        $items = ProductLogic::most_reviewed_products($zone_id, $request['limit'], $request['offset'], $type);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_discounted_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');
        $category_ids = $request->query('category_ids', '');

        $zone_id = $request->header('zoneId');
        $items = ProductLogic::discounted_products($zone_id, $request['limit'], $request['offset'], $type, $category_ids);
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_cart_suggest_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');

        $type = $request->query('type', 'all');
        $recommended = $request->query('recommended');

        $items = ProductLogic::cart_suggest_products($zone_id, $request['store_id'], $request['limit'], $request['offset'], $type, $recommended);
        $items['items'] = Helpers::product_data_formatting($items['items'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_product($id)
    {
        try {
            $item = Item::withCount('whislists')->with(['tags', 'reviews', 'reviews.customer'])->active()
                ->when(config('module.current_module_data'), function ($query) {
                    $query->module(config('module.current_module_data')['id']);
                })
                ->when(is_numeric($id), function ($qurey) use ($id) {
                    $qurey->where('id', $id);
                })
                ->when(!is_numeric($id), function ($qurey) use ($id) {
                    $qurey->where('slug', $id);
                })
                ->first();
            $store = StoreLogic::get_store_details($item->store_id);
            if ($store) {
                $category_ids = DB::table('items')
                    ->join('categories', 'items.category_id', '=', 'categories.id')
                    ->selectRaw('categories.position as positions, IF((categories.position = "0"), categories.id, categories.parent_id) as categories')
                    ->where('items.store_id', $item->store_id)
                    ->where('categories.status', 1)
                    ->groupBy('categories', 'positions')
                    ->get();

                $store = Helpers::store_data_formatting($store);
                $store['category_ids'] = array_map('intval', $category_ids->pluck('categories')->toArray());
                $store['category_details'] = Category::whereIn('id', $store['category_ids'])->get();
                $store['price_range']  = Item::withoutGlobalScopes()->where('store_id', $item->store_id)
                    ->select(DB::raw('MIN(price) AS min_price, MAX(price) AS max_price'))
                    ->get(['min_price', 'max_price']);
            }
            $item = Helpers::product_data_formatting($item, false, false, app()->getLocale());
            $item['store_details'] = $store;
            return response()->json($item, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
            ], 404);
        }
    }

    public function get_related_products(Request $request, $id)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        if (Item::find($id)) {
            $items = ProductLogic::get_related_products($zone_id, $id);
            $items = Helpers::product_data_formatting($items, true, false, app()->getLocale());
            return response()->json($items, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
        ], 404);
    }
    public function get_related_store_products(Request $request, $id)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        if (Item::find($id)) {
            $items = ProductLogic::get_related_store_products($zone_id, $id);
            $items = Helpers::product_data_formatting($items, true, false, app()->getLocale());
            return response()->json($items, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
        ], 404);
    }

    public function get_recommended(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');
        $filter = $request->query('filter', 'all');

        $zone_id = $request->header('zoneId');
        $items = ProductLogic::recommended_items($zone_id, $request->store_id, $request['limit'], $request['offset'], $type, $filter);
        $items['items'] = Helpers::product_data_formatting($items['items'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_set_menus()
    {
        try {
            $items = Helpers::product_data_formatting(Item::active()->with(['rating'])->where(['set_menu' => 1, 'status' => 1])->get(), true, false, app()->getLocale());
            return response()->json($items, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Set menu not found!']
            ], 404);
        }
    }

    public function get_product_reviews(Request $request, $item_id)
    {
        if (isset($request['limit']) && ($request['limit'] != null) && isset($request['offset']) && ($request['offset'] != null)) {

            $reviews = Review::with(['customer', 'item'])->where(['item_id' => $item_id])->active()->paginate($request['limit'], ['*'], 'page', $request['offset']);
            $total = $reviews->total();
        } else {

            $reviews = Review::with(['customer', 'item'])->where(['item_id' => $item_id])->active()->get();
            $total = $reviews->count();
        }

        $storage = [];
        foreach ($reviews as $temp) {
            $temp['attachment'] = json_decode($temp['attachment']);
            $temp['item_name'] = null;
            if ($temp->item) {
                $temp['item_name'] = $temp->item->name;
                if (count($temp->item->translations) > 0) {
                    $translate = array_column($temp->item->translations->toArray(), 'value', 'key');
                    $temp['item_name'] = $translate['name'];
                }
            }

            unset($temp['item']);
            array_push($storage, $temp);
        }

        $data =  [
            'total_size' => $total,
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'reviews' => $storage
        ];

        return response()->json($data, 200);
    }

    public function get_product_rating($id)
    {
        try {
            $item = Item::find($id);
            $overallRating = ProductLogic::get_overall_rating($item->reviews);
            return response()->json(floatval($overallRating[0]), 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function submit_product_review(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required',
            'order_id' => 'required',
            'comment' => 'required',
            'rating' => 'required|numeric|max:5',
        ]);

        $order = Order::find($request->order_id);
        if (isset($order) == false) {
            $validator->errors()->add('order_id', translate('messages.order_data_not_found'));
        }

        $item = Item::find($request->item_id);
        if (isset($order) == false) {
            $validator->errors()->add('item_id', translate('messages.item_not_found'));
        }

        $multi_review = Review::where(['item_id' => $request->item_id, 'user_id' => $request->user()->id, 'order_id' => $request->order_id])->first();
        if (isset($multi_review)) {
            return response()->json([
                'errors' => [
                    ['code' => 'review', 'message' => translate('messages.already_submitted')]
                ]
            ], 403);
        } else {
            $review = new Review;
        }

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image_array = [];
        if (!empty($request->file('attachment'))) {
            foreach ($request->file('attachment') as $image) {
                if ($image != null) {
                    if (!Storage::disk('public')->exists('review')) {
                        Storage::disk('public')->makeDirectory('review');
                    }
                    array_push($image_array, Storage::disk('public')->put('review', $image));
                }
            }
        }

        $order?->OrderReference?->update([
            'is_reviewed' => 1
        ]);

        $review->user_id = $request->user()->id;
        $review->item_id = $request->item_id;
        $review->order_id = $request->order_id;
        $review->module_id = $order->module_id;
        $review->comment = $request->comment;
        $review->rating = $request->rating;
        $review->attachment = json_encode($image_array);
        $review->save();

        if ($item->store) {
            $store_rating = StoreLogic::update_store_rating($item->store->rating, (int)$request->rating);
            $item->store->rating = $store_rating;
            $item->store->save();
        }

        $item->rating = ProductLogic::update_rating($item->rating, (int)$request->rating);
        $item->avg_rating = ProductLogic::get_avg_rating(json_decode($item->rating, true));
        $item->save();
        $item->increment('rating_count');

        return response()->json(['message' => translate('messages.review_submited_successfully')], 200);
    }

    public function item_or_store_search(Request $request)
    {

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        if (!$request->hasHeader('longitude') || !$request->hasHeader('latitude')) {
            $errors = [];
            array_push($errors, ['code' => 'longitude-latitude', 'message' => translate('messages.longitude-latitude_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
        $zone_id = $request->header('zoneId');
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $key = explode(' ', $request->name);

        $items = Item::active()->whereHas('store', function ($query) use ($zone_id) {
            $query->when(config('module.current_module_data'), function ($query) {
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function ($query) {
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orwhere('name', 'like', "%{$value}%")->orWhere('description', 'like', "%{$value}%");
                }
                $q->orWhereHas('translations', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('value', 'like', "%{$value}%");
                        };
                    });
                });
                $q->orWhereHas('tags', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('tag', 'like', "%{$value}%");
                        };
                    });
                });
                $q->orWhereHas('category.parent', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('name', 'like', "%{$value}%");
                        };
                    });
                });
                $q->orWhereHas('category', function ($query) use ($key) {
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('name', 'like', "%{$value}%");
                        };
                    });
                });
            })
            ->limit(50)
            ->get(['id', 'name', 'image']);

        $stores = Store::whereHas('zone.modules', function ($query) {
            $query->where('modules.id', config('module.current_module_data')['id']);
        })->withOpen($longitude ?? 0, $latitude ?? 0)->with(['discount' => function ($q) {
            return $q->validate();
        }])->weekday()->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
        })
            ->when(config('module.current_module_data'), function ($query) use ($zone_id) {
                $query->module(config('module.current_module_data')['id']);
                if (!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->active()
            ->limit(50)
            ->select(['id', 'name', 'logo'])
            ->get();

        return [
            'items' => $items,
            'stores' => $stores
        ];
    }

    public function get_store_condition_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $zone_id = $request->header('zoneId');

        $type = $request->query('type', 'all');
        $limit = $request['limit'];
        $offset = $request['offset'];

        $paginator = Item::whereHas('module.zones', function ($query) use ($zone_id) {
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
            ->whereHas('store', function ($query) use ($zone_id) {
                $query->whereIn('zone_id', json_decode($zone_id, true))->whereHas('zone.modules', function ($query) {
                    $query->when(config('module.current_module_data'), function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                });
            })
            ->whereHas('pharmacy_item_details', function ($q) {
                return $q->whereNotNull('common_condition_id');
            })
            ->when(is_numeric($request->store_id), function ($qurey) use ($request) {
                $qurey->where('store_id', $request->store_id);
            })
            ->when(!is_numeric($request->store_id), function ($query) use ($request) {
                $query->whereHas('store', function ($q) use ($request) {
                    $q->where('slug', $request->store_id);
                });
            })
            ->active()->type($type)->latest()->paginate($limit, ['*'], 'page', $offset);
        $data = [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
        $data['products'] = Helpers::product_data_formatting($data['products'], true, false, app()->getLocale());
        return response()->json($data, 200);
    }

    public function get_popular_basic_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id') ?? null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $limit = $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;

        $items = ProductLogic::get_popular_basic_products($zone_id, $limit, $offset, $type, $request['store_id'], $request['category_id'], $min, $max, $product_id);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }
}
