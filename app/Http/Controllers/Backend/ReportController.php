<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\Models\OrderDetails;
use App\Models\Orders;
use App\Models\Products;
use App\Models\ProductVariant;
use App\Models\VariantColors;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        if (!$fromDate || !$toDate) {
            $fromDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $toDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        } else {
            $fromDate = Carbon::parse($fromDate)->format('Y-m-d');
            $toDate = Carbon::parse($toDate)->format('Y-m-d');
        }

        $productsSold = OrderDetails::with('variantColors.variant.product')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->selectRaw('variant_color_id, SUM(quantity) as total_sold, SUM(total_price) as total_revenue')
            ->groupBy('variant_color_id')
            ->get();

        $report = $productsSold->map(function ($product) use ($fromDate, $toDate) {
            $variantColor = VariantColors::with('color', 'variant.storage')->find($product->variant_color_id);

            if (!$variantColor) {
                return [
                    'product_name' => 'Unknown product',
                    'variant_name' => 'N/A',
                    'color_name' => 'N/A',
                    'total_sold' => $product->total_sold,
                    'stock' => 0,
                    'revenue' => 0,
                    'profit' => 0,
                    'quantity_imported' => 0,
                    'warehouse_price' => 0,
                    'offer_price' => 0,
                    'inventory_value' => 0,
                ];
            }

            // Get product details
            $productVariant = $variantColor->variant;
            $productModel = Products::find($productVariant->pro_id);

            if (!$productModel) {
                return [
                    'product_name' => 'Unknown product',
                    'variant_name' => 'N/A',
                    'color_name' => 'N/A',
                    'total_sold' => $product->total_sold,
                    'stock' => 0,
                    'revenue' => 0,
                    'profit' => 0,
                    'quantity_imported' => 0,
                    'warehouse_price' => 0,
                    'offer_price' => 0,
                    'inventory_value' => 0,
                ];
            }

            // Extract product details including warehouse price and offer price
            $productName = $productModel->name;
            $variantName = $productVariant->storage->GB;
            $colorName = $variantColor->color->name;

            // Tính tồn kho từ các tháng trước
            $previousStock = WarehouseDetails::where('variant_color_id', $variantColor->id)
                ->whereHas('warehouse', function ($query) use ($fromDate) {
                    $query->whereDate('import_date', '<', $fromDate);
                })
                ->sum('quantity') - OrderDetails::where('variant_color_id', $variantColor->id)
                ->whereDate('created_at', '<', $fromDate)
                ->sum('quantity');

            // Lấy số lượng nhập kho trong tháng hiện tại
            $newImports = WarehouseDetails::where('variant_color_id', $variantColor->id)
                ->whereHas('warehouse', function ($query) use ($fromDate, $toDate) {
                    $query->whereBetween('import_date', [$fromDate, $toDate]);
                })
                ->sum('quantity');

            // Tính số lượng tồn kho cuối cùng sau khi bán hàng trong tháng
            $totalSold = $product->total_sold;
            $remainingStock = $previousStock + $newImports - $totalSold;

            // Lấy giá nhập kho trung bình
            $warehousePrice = WarehouseDetails::where('variant_color_id', $variantColor->id)
                ->whereHas('warehouse', function ($query) use ($toDate) {
                    $query->whereDate('import_date', '<=', $toDate);
                })
                ->avg('warehouse_price');

            $offerPrice = $variantColor->offer_price;

            // Tính lợi nhuận và giá trị tồn kho
            $profit = ($offerPrice * $totalSold) - ($warehousePrice * $totalSold);

            // Calculate inventory value (remaining stock * warehouse price)
            $inventoryValue = $remainingStock * $warehousePrice;

            return [
                'product_name' => $productName,
                'variant_name' => $variantName,
                'color_name' => $colorName,
                'total_sold' => $totalSold,
                'quantity_imported' => $newImports,
                'stock' => $remainingStock,
                'revenue' => $totalSold * $offerPrice, // Revenue from sold items
                'profit' => $profit, // Profit from sold items
                'inventory_value' => $inventoryValue, // Inventory value of remaining stock
                'warehouse_price' => $warehousePrice,
                'offer_price' => $offerPrice,
                'new_imports' => $newImports,
            ];
        });

        $totalQuantityImported = $report->sum('quantity_imported');
        $totalSold = $report->sum('total_sold');
        $totalInventoryValue = $report->sum('inventory_value');
        $totalRevenue = $report->sum('revenue');
        $totalProfit = $report->sum('profit');


        return view('backend.admin.reports.index', compact(
            'report',
            'totalQuantityImported',
            'totalSold',
            'totalInventoryValue',
            'totalRevenue',
            'totalProfit',
            'fromDate',
            'toDate'
        ));
    }





    public function reportByCategory(Request $request)
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        if (!$fromDate || !$toDate) {
            $fromDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $toDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        } else {
            $fromDate = Carbon::parse($fromDate)->format('Y-m-d');
            $toDate = Carbon::parse($toDate)->format('Y-m-d');
        }

        // Lấy danh sách các danh mục đã có sản phẩm bán trong khoảng thời gian
        $categoriesSold = OrderDetails::with('variantColors.variant.product.category')
            ->whereDate('order_details.created_at', '>=', $fromDate)
            ->whereDate('order_details.created_at', '<=', $toDate)
            ->selectRaw('products.cate_id as category_id, SUM(order_details.quantity) as total_sold, SUM(order_details.total_price) as total_revenue')
            ->join('variant_colors', 'order_details.variant_color_id', '=', 'variant_colors.id')
            ->join('product_variants', 'variant_colors.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.pro_id', '=', 'products.id')
            ->groupBy('products.cate_id')
            ->get();


        // Tính toán doanh thu và lợi nhuận cho từng danh mục
        $report = $categoriesSold->map(function ($categoryData) {
            $category = Categories::find($categoryData->category_id);

            if (!$category) {
                return [
                    'category_name' => 'Unknown Category',
                    'total_sold' => $categoryData->total_sold,
                    'revenue' => $categoryData->total_revenue,
                    'profit' => 0,
                    'quantity_imported' => 0,
                ];
            }

            // Tính tổng chi phí của tất cả sản phẩm đã bán trong danh mục
            $productsInCategory = Products::where('cate_id', $category->id)->get();
            $totalCost = $productsInCategory->sum(function ($product) use ($categoryData) {
                // Lấy tổng số lượng bán của từng sản phẩm trong khoảng thời gian
                $totalSoldQuantity = OrderDetails::where('variant_color_id', $categoryData->variant_color_id)
                    ->whereHas('variantColors.variant.product', function ($query) use ($product) {
                        $query->where('products.id', $product->id);
                    })
                    ->sum('quantity');

                return $product->cost_price * $totalSoldQuantity;
            });

            $totalQuantityImported = $productsInCategory->sum('quantity');

            return [
                'category_name' => $category->name,
                'total_sold' => $categoryData->total_sold,
                'revenue' => $categoryData->total_revenue,
                'profit' => $categoryData->total_revenue - $totalCost,
                'quantity_imported' => $totalQuantityImported,
            ];
        });

        // Tính tổng cộng cho các cột
        $totalQuantityImported = $report->sum('quantity_imported');
        $totalSold = $report->sum('total_sold');
        $totalRevenue = $report->sum('revenue');
        $totalProfit = $report->sum('profit');

        return view('backend.admin.reports.byCategory', compact(
            'report',
            'totalQuantityImported',
            'totalSold',
            'totalRevenue',
            'totalProfit',
            'fromDate',
            'toDate'
        ));
    }
}