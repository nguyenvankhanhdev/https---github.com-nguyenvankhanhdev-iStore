<?php

namespace App\DataTables;

use App\Models\FlashSale;
use App\Models\FlashSales;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class FlashSalesDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('action', function ($query) {
                $editBtn = "<a href='" . route('admin.flash-sale.edit', $query->id) . "' class='btn btn-primary'><i class='far fa-edit'></i></a>";
                $deleteBtn = "<a href='" . route('admin.flash-sale.destroy', $query->id) . "' class='btn btn-danger ml-2 delete-item'><i class='far fa-trash-alt'></i></a>";
                $moreBtn = '<div class="dropdown dropleft d-inline">
            <button class="btn btn-primary dropdown-toggle ml-1" type="button" id="dropdownMenuButton2" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-cog"></i>
            </button>
            <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
               <a class="dropdown-item has-icon" href="' . route('admin.flash-sale-item.index', ['flashsale' => $query->id]) . '"><i class="far fa-heart"></i>Flash Sale Item</a>
            </div>
          </div>';
                return $editBtn . $deleteBtn . $moreBtn;
            })
            ->addColumn('status', function ($query) {
                if ($query->status == 1) {
                    $button = '<label class="custom-switch mt-2">
                        <input type="checkbox" checked name="custom-switch-checkbox" data-id="' . $query->id . '" class="custom-switch-input change-status" >
                        <span class="custom-switch-indicator"></span>
                    </label>';
                } else {
                    $button = '<label class="custom-switch mt-2">
                        <input type="checkbox" name="custom-switch-checkbox" data-id="' . $query->id . '" class="custom-switch-input change-status">
                        <span class="custom-switch-indicator"></span>
                    </label>';
                }
                return $button;
            })
            ->editColumn('start_date', function ($query) {
                return date('d-m-Y', strtotime($query->start_date));
            })
            ->editColumn('end_date', function ($query) {
                return date('d-m-Y', strtotime($query->end_date));
            })
            ->addColumn('empty_column', function ($query) {
                return ''; // Không có nội dung trong cột
            })
            ->rawColumns(['action', 'status'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(FlashSales $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('flashsales-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            //->dom('Bfrtip')
            ->orderBy(1)
            ->selectStyleSingle()
            ->buttons([
                Button::make('excel'),
                Button::make('csv'),
                Button::make('pdf'),
                Button::make('print'),
                Button::make('reset'),
                Button::make('reload')
            ])
            ->parameters([
                'scrollX' => true, // Bật chế độ cuộn ngang
                'responsive' => true, // Hỗ trợ giao diện responsive
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [

            Column::computed('DT_RowIndex')
                ->title('STT')
                ->width('5%')
                ->searchable(false)
                ->orderable(false)
                ->printable(false)
                ->exportable(false),
            Column::make('start_date')->title('Ngày bắt đầu')->width('25%'),
            Column::make('end_date')->title('Ngày kết thúc')->width('25%'),
            Column::make('status')->title('Trạng thái')->width('25%'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width('30%')
                ->addClass('text-center'),
                Column::make('empty_column') // Cột trống
                ->title('')  // Không hiển thị tiêu đề
                ->orderable(false) // Không thể sắp xếp
                ->searchable(false) // Không thể tìm kiếm
                ->className('empty-column'), // Cột trống
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'FlashSales_' . date('YmdHis');
    }
}
