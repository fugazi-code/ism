<?php

namespace App\Exports;

use App\SalesOrder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles as WithStylesAlias;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SOTotalExcel implements FromQuery, WithHeadings, WithStylesAlias, WithColumnWidths
{
    public $start;
    public $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end   = $end;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function query()
    {
        return (new SalesOrder())->total($this->start, $this->end)
            ->selectRaw('so_no,customers.name,grand_total,agent,payment_status,vat_type,DATE(sales_orders.updated_at) as updated_date');
    }

    public function headings()
    : array
    {
        return ['SO No.', 'Vendor Name', 'Grand Total', 'Agent', 'Payment Status','Vat Type', 'Date Of Purchased'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths()
    : array
    {
        return [
            'A' => 10,
            'B' => 40,
            'C' => 20,
            'D' => 20,
            'E' => 20,
            'F' => 30,
            'G' => 30,
            'H' => 30,
        ];
    }
}
