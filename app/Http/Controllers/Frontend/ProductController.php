<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Accessories;
use App\Models\Categories;
use App\Models\Products;
use App\Models\ProductVariant;
use App\Models\SubCategories;
use Illuminate\Http\Request;
use App\Models\StorageProduct;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\VariantColors;
use App\Models\Comments;
use App\Models\Ratings;
use function App\Helper\getTotal;
use Illuminate\Support\Facades\Session;

class ProductController extends Controller
{
    public function productsIndex(Request $request)
    {
        $productsSale = Products::where('status', 1)
            ->where('product_type', 'sale_product')
            ->orderBy('id', 'DESC')
            ->paginate(6);

        $productsNewArrival = Products::where('status', 1)
            ->where('product_type', 'new_arrival')
            ->orderBy('id', 'DESC')
            ->paginate(6);

        $productsFeatured = Products::where('status', operator: 1)
            ->where('product_type', 'featured_product')
            ->orderBy('id', 'DESC')
            ->paginate(6);

        $productsTop = Products::where('status', 1)
            ->where('product_type', 'top_product')
            ->orderBy('id', 'DESC')
            ->paginate(6);

        $productsBest = Products::where('status', 1)
            ->where('product_type', 'best_product')
            ->orderBy('id', 'DESC')
            ->paginate(6);

        return view('frontend.user.layouts.section_cate', compact('productsSale', 'productsNewArrival', 'productsFeatured', 'productsTop', 'productsBest'));
    }

    public function searchProducts(Request $request)
    {
        $search = $request->input('search');

        $accessoryCategoryId = Categories::where('slug', 'phu-kien-linh-kien')->value('id');

        $accessorySubCategoryIds = SubCategories::where('cate_id', $accessoryCategoryId)->pluck('id')->toArray();

        $isAccessorySearch = false;

        if (!empty($search)) {
            $isAccessorySearch = Categories::where('id', $accessoryCategoryId)
                ->where('name', 'LIKE', "%{$search}%")
                ->exists() ||
                SubCategories::whereIn('id', $accessorySubCategoryIds)
                ->where('name', 'LIKE', "%{$search}%")
                ->exists();
        }

        $products = Products::with(['category', 'subCategory'])
            ->where('status', 1)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%")
                        ->orWhereHas('category', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('subCategory', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%");
                        });
                });
            })
            ->when(!$isAccessorySearch, function ($query) use ($accessorySubCategoryIds) {
                $query->whereDoesntHave('subCategory', function ($q) use ($accessorySubCategoryIds) {
                    $q->whereIn('id', $accessorySubCategoryIds);
                });
            })
            ->when($isAccessorySearch, function ($query) use ($accessorySubCategoryIds) {
                $query->whereHas('subCategory', function ($q) use ($accessorySubCategoryIds) {
                    $q->whereIn('id', $accessorySubCategoryIds);
                });
            })
            ->orderByRaw(
                "CASE
                    WHEN name LIKE ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END",
                ["{$search}%", "%{$search}%"]
            )
            ->orderBy('id', 'DESC')
            ->paginate(6);

        $productCount = $products->total();

        return view('frontend.user.home.search_results', compact('products', 'search', 'productCount'));
    }

    public function productCategories(Request $request)
    {
        $products = collect();
        $subcategories = collect();
        $categories = null;
        if ($request->has('categories')) {
            $categories = Categories::where('slug', $request->categories)->firstOrFail();
            $subcategories = SubCategories::where('cate_id', $categories->id)->get();
            $products = Products::where([
                'cate_id' => $categories->id,
                'status' => 1,
            ])->get();
        }
        return view('frontend.user.categories.index', compact('products', 'categories', 'subcategories'));
    }
    public function showProduct(string $slug, Request $request)
    {
        $product = Products::with(relations: ['productImages', 'variants.variantColors', 'ratings', 'category', 'subcategory'])->where(column: [
            'slug' => $slug,
            'status' => 1
        ])->first();
        $selectedVariantId = $request->query('variant', $product->variants->first()->id);
        $colors = VariantColors::where('variant_id', $selectedVariantId)->get();
        $accessories = Accessories::with(['product', 'subCategory'])
            ->where('sub_cate_id', $product->sub_cate_id)
            ->get();

        if ($accessories->isNotEmpty()) {
            $proIds = $accessories->pluck('pro_id')->unique(); // Lấy danh sách sub_cate_id không trùng lặp

            $sameProducts = collect();

            // Lấy 1 sản phẩm từ mỗi sub_cate_id
            foreach ($proIds as $subCateId) {
                $products = Products::with(['variants.variantColors'])
                    ->where('id', $subCateId)
                    ->limit(1) // Lấy 1 sản phẩm từ mỗi sub_cate_id
                    ->get();
                $sameProducts = $sameProducts->merge($products); // Gộp các sản phẩm vào collection
            }
            $sameProducts = $sameProducts->take(4);
        } else {
            $sameProducts = collect();
        }

        if (Auth::id() > 0) {
            $userID = Auth::id();
            $user = User::find(Auth::id());
            $comment = Comments::with('user')
                ->where([
                    'pro_id' => $product->id,
                    'status' => 0,
                    'cmt_id' => 0
                ])
                ->orderBy('created_at', 'desc')
                ->paginate(6);

            $averageRating = Ratings::getAverageRating($product->id);

            $product = Products::find($product->id);
            if ($product) {
                $product->point = $averageRating;
                $product->save();
            }
            $ratingOfProduct = Ratings::where('pro_id', $product->id)->get();
            $ratingsCount = Ratings::getCountByStar($product->id);
            $countRatingProduct = Ratings::countRatingsByProduct($product->id);
            return view('frontend.user.home.product_details', compact('ratingOfProduct', 'countRatingProduct', 'product', 'user', 'comment', 'selectedVariantId', 'colors', 'ratingsCount', 'sameProducts'));
        } else {
            $comment = Comments::with('user')
                ->where([
                    'pro_id' => $product->id,
                    'status' => 0,
                    'cmt_id' => 0

                ])
                ->orderBy('created_at', 'desc')
                ->paginate(6); // Phân trang với 6 bình luận mỗi trang
            //->get();
            $averageRating = Ratings::getAverageRating($product->id);

            // Cập nhật lại điểm trung bình của sản phẩm
            $product = Products::find($product->id);
            if ($product) {
                $product->point = $averageRating; // Cập nhật lại thuộc tính point
                $product->save();
            }
            $ratingOfProduct = Ratings::where('pro_id', $product->id)->get();
            $ratingsCount = Ratings::getCountByStar($product->id);
            $countRatingProduct = Ratings::countRatingsByProduct($product->id);


            return view('frontend.user.home.product_details', compact('ratingOfProduct', 'countRatingProduct', 'product',  'comment', 'selectedVariantId', 'colors', 'ratingsCount', 'sameProducts'));
        }
    }



    public function productSubCategories(Request $request)
    {
        if ($request->has('subcategories')) {
            $subcategory = SubCategories::where('slug', $request->subcategories)->first();
            $categories = Categories::where('id', $subcategory->cate_id)->firstOrFail();
            $subcategories = SubCategories::where('cate_id', $categories->id)->get();
            $products = Products::where([
                'sub_cate_id' => $subcategory->id,
                'status' => 1,
            ])
                ->paginate(5);

            foreach ($products as $product) {
                $product->variants = ProductVariant::where('pro_id', $product->id)->get();
            };
        }
        return view('frontend.user.categories.index', compact('products', 'categories', 'subcategories'));
    }
    public function getPrice(Request $request)
    {
        $variant = $request->variant_id;
        $color = $request->color_id;
        $price = VariantColors::where([
            'color_id' => $color,
            'variant_id' => $variant
        ])->first();
        $storage = ProductVariant::where([
            'id' => $variant
        ])->first();
        return response()->json(['price' => $price, 'storage' => $storage]);
    }


    public function rating(Request $request)
    {

        try {
            // Kiểm tra xem người dùng đã đăng nhập chưa
            if (!Auth::check()) {
                return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
            }

            $userId = Auth::id();
            $productId = $request->pro_id;

            $hasPurchased = Products::hasUserPurchasedProduct($userId, $productId);

            if (!$hasPurchased) {
                return response()->json(['message' => 'Bạn chưa mua sản phẩm này'], 403);
            }

            // Kiểm tra xem người dùng đã đánh giá sản phẩm chưa
            $existingRating = Ratings::where('user_id', $userId)
                ->where('pro_id', $productId)
                ->first();

            // Nếu người dùng đã đánh giá, cập nhật lại điểm
            if ($existingRating) {
                $existingRating->point = $request->point;
                $existingRating->save();
                $message = 'Bạn đã sửa đánh giá sản phẩm thành công';
            } else {
                // Nếu chưa có đánh giá, tạo đánh giá mới
                $rating = new Ratings();
                $rating->point = $request->point;
                $rating->user_id = $userId;
                $rating->pro_id = $productId;
                $rating->save();
                $message = 'Đánh giá của bạn đã được lưu';
            }

            // Tính điểm trung bình của sản phẩm
            $averageRating = Ratings::getAverageRating($productId);

            // Cập nhật lại điểm trung bình của sản phẩm
            $product = Products::find($productId);
            if ($product) {
                $product->point = $averageRating; // Cập nhật lại thuộc tính point
                $product->save();
            }
            $ratingsCount = Ratings::getCountByStar($product->id);
            $infoRating = Ratings::where('pro_id', $product->id)
                ->where('user_id', $userId)
                ->first();
            // Trả về thông báo thành công
            return response()->json(['infoRating' => $infoRating, 'message' => $message, 'averageRating' => $averageRating, 'ratingsCount' => $ratingsCount], 200);
        } catch (\Exception $e) {
            \Log::error($e->getMessage()); // Ghi lại lỗi vào log
            return response()->json(['message' => 'Đã xảy ra lỗi hệ thống.'], 500);
        }
    }
    public function getPriceByVariantAndColor(Request $request)
    {
        $request->validate([
            'variant_id' => 'required',
            'color_id' => 'required',
        ]);
        $variantColors = VariantColors::where([
            'variant_id' => $request->variant_id,
            'color_id' => $request->color_id,
        ])->firstOrFail();

        return response()->json([
            'status' => 'success',
            'price' => $variantColors,
            'quantity'=> $variantColors->quantity,
            'storage' => $variantColors->variant->storage,
        ]);
    }
    public function getPriceByVariant(Request $request)
    {
        $variant = ProductVariant::find($request->variantId);
        $firstPrice = $variant->variantColors->first();
        return response()->json([
            'status' => 'success',
            'variantColors' => $firstPrice,
        ]);
    }
}
