<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BeneficiaryExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    use Exportable;

    public function __construct(
        private readonly \Illuminate\Database\Eloquent\Builder $query,
        private readonly string $title = 'Beneficiaries'
    ) {}

    public function query()
    {
        return $this->query;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return [
            'BIN',
            'Full Name',
            'Gender',
            'Civil Status',
            'Birthdate',
            'Contact Number',
            'Email',
            'Barangay',
            'Municipality',
            'Province',
            'ZIP Code',
            'Annual Income (₱)',
            'Household Size',
            'Status',
            'Token Status',
            'Registered By',
            'Registration Date',
        ];
    }

    public function map($beneficiary): array
    {
        return [
            $beneficiary->bin,
            $beneficiary->family_head_name,
            $beneficiary->gender,
            $beneficiary->civil_status,
            optional($beneficiary->family_head_birthdate)->format('Y-m-d') ?? '',
            $beneficiary->contact_number,
            $beneficiary->email ?? '',
            $beneficiary->barangay,
            $beneficiary->municipality,
            $beneficiary->province,
            $beneficiary->zip_code,
            number_format((float) $beneficiary->annual_income, 2),
            $beneficiary->household_size,
            $beneficiary->is_active ? 'Active' : 'Inactive',
            ucfirst($beneficiary->token_status),
            $beneficiary->registeredBy?->name ?? '',
            $beneficiary->created_at?->format('Y-m-d H:i') ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row bold + background
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
