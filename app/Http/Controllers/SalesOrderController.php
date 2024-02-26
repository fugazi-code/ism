<?php

namespace App\Http\Controllers;

use App\Exports\SalesReportExcel;
use App\Http\Controllers\Traits\HasProductDetail;
use App\PaymentMethod;
use App\Preference;
use App\Product;
use App\ProductDetail;
use App\SalesOrder;
use App\PrintSetting;
use App\Summary;
use App\Supply;
use App\Abilities;
use App\Permission;
use App\Assigned_Role;
use Maatwebsite\Excel\Facades\Excel;
use PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\DataTables;
use DB;

class SalesOrderController extends Controller
{
    use HasProductDetail;

    public function index()
    {

        return view('sales');
    }
    public function stock_out()
    {

        return view('sales_stock_out');
    }

    public function table(Request $request)
    {
        Supply::recalibrate();
                $vendors = SalesOrder::query()
            ->selectRaw('sales_orders.*, users.name as username, customers.name as customer_name,
            shipped_date,summaries.grand_total')
            ->leftJoin('summaries', 'summaries.sales_order_id', '=', 'sales_orders.id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->leftJoin('users', 'users.id', '=', 'sales_orders.assigned_to')
            ->whereIn('sales_orders.status', ['Sales', 'Project']);

            if ($request->filled('filter_payment')) {
                $vendors->where('sales_orders.payment_status', $request->input('filter_payment'));
            }
            if ($request->filled('filter_status')) {
                $vendors->where('sales_orders.status', $request->input('filter_status'));
            }
            if ($request->filled('filter_delivery_status')) {
                $vendors->where('sales_orders.delivery_status', $request->input('filter_delivery_status'));
            }
            if ($request->filled('filter_vat')) {
                $vendors->where('sales_orders.vat_type', $request->input('filter_vat'));
            }

        return DataTables::of($vendors)->setTransformer(function ($data)  {
            $data                   = $data->toArray();
            $data['created_at']     = Carbon::parse($data['created_at'])->format('F j, Y');
            $data['shipped_date_display'] = $data['shipped_date'] ? Carbon::parse($data['shipped_date'])->format('F j, Y') : 'No Date';
            $data['due_date']       = isset($data['due_date']) ? Carbon::parse($data['due_date'])->format('F j, Y') : 'No Date';
            $data['can_be_shipped'] = 1;

            $product_details = $this->getProductDetail($data['id']);
            foreach ($product_details as $products) {
                $diff = $products->quantity - $products->qty;

                if ($diff < 0 && $products->type == 'limited') {
                    $data['can_be_shipped'] = 0;
                }
            }

            return $data;
        })->make(true);
    }

    public function table_stockout(Request $request)
    {
        Supply::recalibrate();
                $vendors = SalesOrder::query()
            ->selectRaw('sales_orders.*, users.name as username, customers.name as customer_name,
            shipped_date,summaries.grand_total')
            ->leftJoin('summaries', 'summaries.sales_order_id', '=', 'sales_orders.id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->leftJoin('users', 'users.id', '=', 'sales_orders.assigned_to')
            ->where('sales_orders.status', 'Stock Out');

            if ($request->filled('filter_payment')) {
                $vendors->where('sales_orders.payment_status', $request->input('filter_payment'));
            }
            if ($request->filled('filter_status')) {
                $vendors->where('sales_orders.status', $request->input('filter_status'));
            }
            if ($request->filled('filter_delivery_status')) {
                $vendors->where('sales_orders.delivery_status', $request->input('filter_delivery_status'));
            }
            if ($request->filled('filter_vat')) {
                $vendors->where('sales_orders.vat_type', $request->input('filter_vat'));
            }

        return DataTables::of($vendors)->setTransformer(function ($data)  {
            $data                   = $data->toArray();
            $data['created_at']     = Carbon::parse($data['created_at'])->format('F j, Y');
            $data['shipped_date_display'] = $data['shipped_date'] ? Carbon::parse($data['shipped_date'])->format('F j, Y') : 'No Date';
            $data['due_date']       = isset($data['due_date']) ? Carbon::parse($data['due_date'])->format('F j, Y') : 'No Date';
            $data['can_be_shipped'] = 1;

            $product_details = $this->getProductDetail($data['id']);
            foreach ($product_details as $products) {
                $diff = $products->quantity - $products->qty;

                if ($diff < 0 && $products->type == 'limited') {
                    $data['can_be_shipped'] = 0;
                }
            }

            return $data;
        })->make(true);
    }


    public function create()
    {
        $sales_order = collect([
            "id"              => "",
            "subject"         => "",
            "customer_id"     => "",
            "owner"           => "",
            "so_no"           => SalesOrder::generate()->newSONo(),
            "agent"           => auth()->user()->name,
            "assigned_to"     => "",
            "fax"             => "",
            "status"          => "Quote",
            "delivery_status" => "Not Shipped",
            "address"         => "",
            "due_date"        => "",
            "payment_method"  => "",
            "payment_status"  => "UNPAID",
            "account_name"    => Preference::status('account_name'),
            "account_no"      => Preference::status('account_no'),
            "tac"             => Preference::status('tac_so_fill'),
            "warranty"        => Preference::status('warranty'),
            "phone"           => "",
            "updated_at"      => Carbon::now()->format('Y-m-d'),
            "vat_type"        => "VAT EX",
        ]);

        $product_details = collect([]);

        $summary = collect([
            "id"             => "",
            "sales_order_id" => "",
            "discount"       => "0",
            "shipping"       => "0",
            "sales_actual"   => "0",
            "sales_tax"      => "0",
            "grand_total"    => "0",
            "sub_total"      => "0",
        ]);
        $paymentMethods  = PaymentMethod::all();

        return view('sales_form', compact('sales_order', 'product_details', 'summary', 'paymentMethods'));
    }

    public function store(Request $request)
    {
        $data                            = $request->input();
        $data['overview']['so_no']       = SalesOrder::generate()->newSONo();
        $data['overview']['assigned_to'] = auth()->id();
        $data['overview']['created_at']  = Carbon::now()->format('Y-m-d');

        $id = DB::table('sales_orders')->insertGetId($data['overview']);

        // Insert in product Details
        $product_details = [];
        $pd              = false;
        if (isset($data['products'])) {
            foreach ($data['products'] as $item) {
                if (count($item) > 2) {
                    $product_details[] = [
                        //'purchase_order_id' => $id,
                        'sales_order_id' => $id,
                        //'product_return_id' => '',
                        'product_id'     => $item['product_id'],
                        'product_name'   => $item['product_name'],
                        'notes'          => $item['notes'],
                        'qty'            => $item['qty'],
                        'selling_price'  => $item['selling_price'],
                        'vendor_price'   => $item['vendor_price'],
                        'discount_item'  => $item['discount_item'],
                    ];
                }
            }

            $pd = DB::table('product_details')->insert($product_details);
        }

        if ($pd) {
            Supply::recalibrate();
        }

        $data['summary']['sales_order_id'] = $id;
        DB::table('summaries')->insert($data['summary']);

        // Record Action in Audit Log
        $name = auth()->user()->name;

        if($name != 'Super Admin') {
            \App\AuditLog::record([
                'name' => $name,
                'inputs' => $request->input(),
                'url' => $request->url(),
                'action_id' => $data['overview']['so_no'],
                'current' => $data['overview']['status'],
                'method' => "CREATED"
            ]);
        }

        return ['success' => true];
    }

    public function update(Request $request)
    {
        $data = $request->input();

        unset($data['overview']['unit']);
        unset($data['overview']['customer_name']);

        // Record Action in Audit Log
        $name = auth()->user()->name;

        if($name != 'Super Admin') {
            \App\AuditLog::record([
                'name' => $name,
                'inputs' => $request->input(),
                'url' => $request->url(),
                'action_id' => $data['overview']['so_no'],
                'current' => $data['overview']['status'],
                'method' => "UPDATED"
            ]);
        }

        // Update Sales Order Info
        SalesOrder::updateInfo($data['overview']);

        // Delete products that have been reset
        DB::table('product_details')->where('sales_order_id', $data['overview']['id'])->delete();

        // Insert in product Details
        $product_details = [];
        $pd              = false;
        if (isset($data['products'])) {
            foreach ($data['products'] as $item) {
                if (count($item) > 2) {
                    $product_details[] = [
                        //'purchase_order_id' => $id,
                        'sales_order_id' => $data['overview']['id'],
                        //'product_return_id' => '',
                        'product_id'     => $item['product_id'],
                        'product_name'   => $item['product_name'],
                        'notes'          => $item['notes'],
                        'qty'            => $item['qty'],
                        'selling_price'  => $item['selling_price'],
                        'vendor_price'   => $item['vendor_price'],
                        'discount_item'  => $item['discount_item'],
                    ];
                }
            }

            $pd = DB::table('product_details')->insert($product_details);
        }

        if ($pd) {
            Supply::recalibrate();
        }

        // Insert to Summary
        DB::table('summaries')->where('sales_order_id', $data['overview']['id'])->delete();
        $data['summary']['sales_order_id'] = $data['overview']['id'];
        DB::table('summaries')->insert($data['summary']);

        return ['success' => true];
    }

    public function destroy(Request $request)
    {
        // Reset supply count based on current product details
        $product_details = ProductDetail::fetchDataSO($request->id);
        foreach ($product_details as $item) {
            if ('Shipped' == $request->delivery_status) {
                if (Product::isLimited($item['product_id'])) {
                    Supply::increCount($item['product_id'], $item['qty']);
                }
            }
        }

        // Record Action in Audit Log
        $name = auth()->user()->name;

        if($name != 'Super Admin') {
            \App\AuditLog::record([
                'name' => $name,
                'inputs' => $request->input(),
                'url' => $request->url(),
                'action_id' => $request->so_no,
                // 'current' => $current,
                'method' => "DELETED"
            ]);
        }

        ProductDetail::query()->where('sales_order_id', $request->id)->delete();
        DB::table('sales_orders')->where('id', $request->id)->delete();
        DB::table('summaries')->where('sales_order_id', $request->id)->delete();

        return ['success' => true];
    }

    public function updatePaymentStatus(Request $request)
    {
        $data = $request->input();

        // Record Action in Audit Log
        $name = auth()->user()->name;

        if($name != 'Super Admin') {
            \App\AuditLog::record([
                'name' => $name,
                'inputs' => $request->input(),
                'url' => $request->url(),
                'action_id' => $data['so_no'],
                'current' => $data['payment_status'],
                'method' => "UPDATED"
            ]);
        }

        DB::table('sales_orders')->where('id', $data['id'])
            ->update(['payment_status' => $data['payment_status']]);

        return ['success' => true];
    }

    public function updateVatStatus(Request $request)
    {
        $data = $request->input();
        DB::table('sales_orders')->where('id', $data['id'])
            ->update(['vat_type' => $data['vat_type']]);

        return ['success' => true];
    }

    public function updateDeliveryStatus(Request $request)
    {
        $data = $request->input();

        // Record Action in Audit Log
        $name = auth()->user()->name;

        if($name != 'Super Admin') {
            \App\AuditLog::record([
                'name' => $name,
                'inputs' => $request->input(),
                'url' => $request->url(),
                'action_id' => $data['so_no'],
                'current' => $data['delivery_status'],
                'method' => "UPDATED"
            ]);
        }

        DB::table('sales_orders')->where('id', $data['id'])
            ->update([
                'delivery_status' => $data['delivery_status'],
                'shipped_date' =>  $data['delivery_status'] == 'Shipped' ? now() : null
            ]);

        return ['success' => true];
    }

    public function updateStatus(Request $request)
    {
        $data = $request->input();
        $salesOrder = DB::table('sales_orders')->where('id', $data['id'])->get()[0];

        if ($salesOrder->status != $data['status']) {

         // Record Action in Audit Log
         $name = auth()->user()->name;

         if($name != 'Super Admin') {
             \App\AuditLog::record([
                 'name' => $name,
                 'inputs' => $request->input(),
                 'url' => $request->url(),
                 'action_id' => $data['so_no'],
                 'current' => $data['status'],
                 'method' => "UPDATED"
             ]);
         }

        DB::table('sales_orders')->where('id', $data['id'])
                ->update([
                    'status' => $data['status'],
                ]);

            return ['success' => true];
        }


        if ($salesOrder->shipped_date != $data['shipped_date']) {
            DB::table('sales_orders')->where('id', $data['id'])
                ->update([
                    'shipped_date' => $data['shipped_date'],
                ]);
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function show($id)
    {
        $data = $this->getOverview($id);
        unset($data['sales_order']['name']);
        $sales_order     = $data['sales_order'];
        $product_details = $data['product_details'];
        $summary         = $data['summary'];
        $paymentMethods  = PaymentMethod::all();

        return view('sales_form', compact('sales_order', 'product_details', 'summary', 'paymentMethods'));
    }

    public function printable($id)
    {
        $data            = $this->getOverview($id);
        $sales_order     = $data['sales_order'];
        $product_details = $data['product_details'];
        $summary         = $data['summary'];
        $sections        = [];
        $cnt             = -1;
        $print_setting   = PrintSetting::query()->first();

        $sales_order->so_no = str_replace('SO', 'WR', $sales_order->so_no);
        $sales_order->tac = preg_replace('/ADDRESS:(.*?)(\bEmail\b.*?$)/s', '---',$sales_order->tac);

        foreach ($product_details as $key => $value) {
            if (count($value) == 1) {
                $sections[] = [
                    $value['category'] => 0,
                ];
                $cnt++;
            } else {
                if ($cnt == -1) {
                    $cnt = 0;
                }
                $total_selling                      = ($value['qty'] * $value['selling_price']) + $value['discount_item'];
                $sections[$cnt][$value['category']] += $total_selling;
            }
        }

        $hold_section = $sections;
        foreach ($hold_section as $index => $section) {
            foreach ($section as $key => $value) {
                $hold_section[$index] = [$this->converToRoman($index + 1) . '. ' . $key => $value];
            }
        }
        $sections = $hold_section;

        $pdf = PDF::loadView(
            'sales_printable',
                [
                'sales_order'     => $sales_order,
                'product_details' => $product_details,
                'summary'         => $summary,
                'sections'        => $sections,
                'print_setting' => $print_setting,
                ]
        );

        // return view('sales_printable', compact('sales_order', 'product_details', 'summary', 'sections', 'print_setting'));
        return $pdf->setPaper('a4')
            ->setTemporaryFolder(public_path())
            ->download('SO ' . $sales_order["so_no"] . ' ' . $sales_order["customer_name"] . '.pdf');
    }

    public function quote($id)
    {
        $data            = $this->getOverview($id);
        $sales_order     = $data['sales_order'];
        $product_details = $data['product_details'];
        $summary         = $data['summary'];
        $sections        = [];
        $cnt             = -1;
        $print_setting   = PrintSetting::query()->first();

        $sales_order->tac = preg_replace('/ADDRESS:(.*?)(\bEmail\b.*?$)/s', '---',$sales_order->tac);

        foreach ($product_details as $key => $value) {
            if (count($value) == 1) {
                $sections[] = [
                    $value['category'] => 0,
                ];
                $cnt++;
            } else {
                $total_selling                      = ($value['qty'] * $value['selling_price']) + $value['discount_item'];
                $sections[$cnt][$value['category']] += $total_selling;
            }
        }

        $hold_section = $sections;
        foreach ($hold_section as $index => $section) {
            foreach ($section as $key => $value) {
                $hold_section[$index] = [$this->converToRoman($index + 1) . '. ' . $key => $value];
            }
        }
        $sections = $hold_section;
        $pdf = PDF::loadView(
            'quote_printable',
            [
                'sales_order'     => $sales_order,
                'product_details' => $product_details,
                'summary'         => $summary,
                'sections'        => $sections,
                'print_setting' => $print_setting,

            ]
        );
        // return view('quote_printable', compact('sales_order', 'product_details', 'summary', 'sections', 'print_setting'));

        return $pdf->setPaper('a4')
            ->setTemporaryFolder(public_path())
            ->download('QTN ' . $sales_order["so_no"] . ' ' . $sales_order["customer_name"] . '.pdf');
    }

    public function deliver($id)
    {
        $data            = $this->getOverview($id);
        $sales_order     = $data['sales_order'];
        $product_details = $data['product_details'];
        $summary         = $data['summary'];
        $sections        = [];
        $cnt             = -1;
        $print_setting   = PrintSetting::query()->first();

        $sales_order->tac = preg_replace('/ADDRESS:(.*?)(\bEmail\b.*?$)/s', '---',$sales_order->tac);
        foreach ($product_details as $key => $value) {
            if (count($value) == 1) {
                $sections[] = [
                    $value['category'] => 0,
                ];
                $cnt++;
            } else {
                $total_selling                      = ($value['qty'] * $value['selling_price']) + $value['discount_item'];
                $sections[$cnt][$value['category']] += $total_selling;
            }
        }

        $hold_section = $sections;
        foreach ($hold_section as $index => $section) {
            foreach ($section as $key => $value) {
                $hold_section[$index] = [$this->converToRoman($index + 1) . '. ' . $key => $value];
            }
        }
        $sections = $hold_section;

        $pdf = PDF::loadView(
            'dr_printable',
            [
                'sales_order'     => $sales_order,
                'product_details' => $product_details,
                'summary'         => $summary,
                'sections'        => $sections,
                'print_setting' => $print_setting,
            ]
        );
        // return view('dr_printable', compact('sales_order', 'product_details', 'summary', 'sections', 'print_setting'));

        return $pdf->setPaper('a4')
            ->setTemporaryFolder(public_path())
            ->download('DR ' . $sales_order["so_no"] . ' ' . $sales_order["customer_name"] . '.pdf');
    }

    public function previewSO($id)
    {
        $data            = $this->getOverview($id);
        $sales_order     = $data['sales_order'];
        $product_details = $data['product_details'];
        $summary         = $data['summary'];

        $sections = [];
        $cnt      = -1;
        foreach ($product_details as $key => $value) {
            if (count($value) == 1) {
                $sections[] = [
                    $value['category'] => 0,
                ];
                $cnt++;
            } else {
                $total_selling                      = ($value['qty'] * $value['selling_price']) + $value['discount_item'];
                $sections[$cnt][$value['category']] += $total_selling;
            }
        }

        $hold_section = $sections;
        foreach ($hold_section as $index => $section) {
            foreach ($section as $key => $value) {
                $hold_section[$index] = [$this->converToRoman($index + 1) . '. ' . $key => $value];
            }
        }
        $sections = $hold_section;

        return view('sales_printable', compact('sales_order', 'product_details', 'summary', 'sections'));
    }

    public function getOverview($id)
    {
        $sales_order = SalesOrder::query()
            ->selectRaw('sales_orders.*, IFNULL(customers.name, \'\') as customer_name, users.name')
            ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->leftJoin('users', 'users.id', '=', 'sales_orders.assigned_to')
            ->where('sales_orders.id', $id)
            ->get()[0];

        $product_details = $this->getProductDetail($id);

        $categories = [];
        foreach ($product_details->toArray() as $value) {
            if (!in_array($value['category'], $categories)) {
                $categories[] = $value['category'];
            }
        }

        $hold = [];
        foreach ($product_details->toArray() as $value) {
            $hold[$value['category']][] = $value;
        }

        if (array_key_exists('DISCOUNT', $hold)) {
            $v = $hold['DISCOUNT'];
            unset($hold['DISCOUNT']);
            $hold['DISCOUNT'] = $v;
        }

        $final = [];
        foreach ($hold as $key => $sub) {
            $final[] = ['category' => $key];
            foreach ($sub as $item) {
                unset($item['name']);
                unset($item['manufacturer']);
                unset($item['description']);
                unset($item['batch']);
                unset($item['color']);
                unset($item['size']);
                unset($item['weight']);
                unset($item['assigned_to']);
                unset($item['id']);
                $final[] = $item;
            }
        }

        $product_details = collect($final);

        $summary = collect([
            'purchase_order_id' => '',
            'sales_order_id'    => '',
            'discount'          => '0',
            'sub_total'         => '0',
            'shipping'          => '0',
            'sales_tax'         => '0',
            'grand_total'       => '0',
        ]);

        if (Summary::query()->where('sales_order_id', $id)->count() > 0) {
            $summary = Summary::query()->where('sales_order_id', $id)->get()[0];
        }

        return [
            'sales_order'     => $sales_order,
            'product_details' => $product_details,
            'summary'         => $summary,
        ];
    }

    public function converToRoman($num)
    {
        $n   = intval($num);
        $res = '';

        //array of roman numbers
        $romanNumber_Array = [
            'M'  => 1000,
            'CM' => 900,
            'D'  => 500,
            'CD' => 400,
            'C'  => 100,
            'XC' => 90,
            'L'  => 50,
            'XL' => 40,
            'X'  => 10,
            'IX' => 9,
            'V'  => 5,
            'IV' => 4,
            'I'  => 1,
        ];

        foreach ($romanNumber_Array as $roman => $number) {
            //divide to get  matches
            $matches = intval($n / $number);

            //assign the roman char * $matches
            $res .= str_repeat($roman, $matches);

            //substract from the number
            $n = $n % $number;
        }

        // return the result
        return $res;
    }

    public function getListShipped(Request $request): array
    {
        $sales_order = SalesOrder::query()
            ->selectRaw("id as id, so_no as text")
            ->where('delivery_status', 'Shipped')
            ->whereRaw("so_no LIKE '%{$request->term}%'");

        return [
            "results" => $sales_order->get(),
        ];
    }

    public function downloadSaleReport(Request $request): BinaryFileResponse
    {
        $date = now()->format('Y-m-d_H:i:s');

        return Excel::download(new SalesReportExcel($request->start, $request->end), "SALES_REPORT_$date.xlsx");
    }

    public function downloadSaleReportAll(Request $request): BinaryFileResponse
    {
        $date = now()->format('Y-m-d_H:i:s');

        return Excel::download(new SalesReportExcel(0, 0), "SALES_REPORT_$date.xlsx");
    }
}
