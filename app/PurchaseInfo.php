<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInfo extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public static function generate()
    {
        return new static;
    }

    public function newPONo()
    {
        if (Preference::verify('po_auto') == 0) {
            return '';
        }
        $po_no_list = $this->newQuery()
            ->where('po_no', 'like', '%PO%')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->get()
            ->toArray();
        $str_length = 5;
        $year       = Carbon::now()->format('y');
        if (isset($po_no_list[0]["po_no"])) {
            $po_no = $po_no_list[0]["po_no"];
        }
        if (count($po_no_list) == 0 || substr(explode('-', $po_no)[0], -2) != $year) {
            $num = 1;
            $str = substr("0000{$num}", -$str_length);

            return 'PO' . $year . '-' . $str;
        } else {
            $numbering = explode('-', $po_no)[1];
            $year      = Carbon::now()->format('y');
            $final_num = (int) $numbering + 1;
            $str       = substr("0000{$final_num}", -$str_length);

            return 'PO' . $year . '-' . $str;
        }
    }

    public static function updateInfo($overview)
    {
        self::query()->where('id', $overview['id'])->update($overview);
    }

    public function total($start, $end)
    {
        return $this->selectRaw('purchase_infos.due_date, purchase_infos.po_no,
        subject,
        vendors.name,
        purchase_infos.payment_status,
        purchase_infos.vat_type,
        summaries.grand_total')
            ->leftJoin('vendors', 'vendors.id', '=', 'purchase_infos.vendor_id')
            ->join('summaries', 'summaries.purchase_order_id', '=', 'purchase_infos.id')
            ->orderBy('purchase_infos.po_no', 'desc')
            ->where('status', 'Received')
            ->whereNull('sales_order_id')
            ->when($start && $end, function ($q) use ($start, $end) {
                $q->whereBetween('purchase_infos.created_at', [$start, $end]);
            });
    }

    public function totalvi($month, $year)
    {

        // return $this->selectRaw('purchase_infos.due_date, purchase_infos.po_no,
        $query = $this->selectRaw('purchase_infos.due_date, purchase_infos.po_no,
                subject,
                vendors.name,
                purchase_infos.payment_status,
                purchase_infos.vat_type,
                summaries.grand_total as grand_total')
            ->leftJoin('vendors', 'vendors.id', '=', 'purchase_infos.vendor_id')
            ->join('summaries', 'summaries.purchase_order_id', '=', 'purchase_infos.id')
            ->orderBy('purchase_infos.po_no', 'desc')
            ->where('status', 'Received')
            ->where('vat_type', 'VAT INC')
            ->whereNull('sales_order_id');

        // If both $month and $year are provided, filter by the specified month and year
        if ($month && $year) {

            $startOfMonth = $year . '-' . $month . '-01';
            $endOfMonth = date('Y-m-t', strtotime($startOfMonth));


            $query->whereBetween('purchase_infos.created_at', [$startOfMonth, $endOfMonth]);
        }

        if ($month && empty($year)) {
            $year = date('Y');
            $startOfMonth = $year . '-' . $month . '-01';
            $endOfMonth = date('Y-m-t', strtotime($startOfMonth));

            $query->whereBetween('purchase_infos.created_at', [$startOfMonth, $endOfMonth]);
        }

        if (empty($month) && $year) {
            $start_month = 01;
            $end_month = 12;
            $startOfMonth = $year . '-' . $start_month . '-01';

            $endOfMonth = $year . '-' . $end_month . '-31';
            $endOfMonth = date('Y-m-t', strtotime($endOfMonth));


            $query->whereBetween('purchase_infos.created_at', [$startOfMonth, $endOfMonth]);

        }

        // Get the sum of grand_total
        // $totalGrandTotal = $query->sum('summaries.grand_total');

        return $query;

    }

    public function pricesAffected($productId)
    {
        return $this->query()
            ->selectRaw('pd.id as pd_id')
            ->join('product_details as pd', 'pd.purchase_order_id', '=', 'purchase_infos.id')
            ->where('purchase_infos.status', 'Ordered')
            ->where('pd.product_id', $productId)
            ->get()
            ->pluck('pd_id');
    }
}
