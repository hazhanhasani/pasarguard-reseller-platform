<?php
namespace App\Services;

use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExportService
{
    public function csv(Builder $query, array $columns, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns));
            foreach ($query->cursor() as $row) {
                $values = [];
                foreach (array_keys($columns) as $key) $values[] = $row->{$key} ?? '';
                fputcsv($out, $values);
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function excel(Builder $query, array $columns, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $columns) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Report"><Table>';
            echo '<Row>';
            foreach ($columns as $title) echo '<Cell><Data ss:Type="String">'.$this->xml((string) $title).'</Data></Cell>';
            echo '</Row>';
            foreach ($query->cursor() as $row) {
                echo '<Row>';
                foreach (array_keys($columns) as $key) {
                    $value = $row->{$key} ?? '';
                    $numeric = $key !== 'label' && is_numeric($value);
                    echo '<Cell><Data ss:Type="'.($numeric ? 'Number' : 'String').'">'.$this->xml((string) $value).'</Data></Cell>';
                }
                echo '</Row>';
            }
            echo '</Table></Worksheet></Workbook>';
        }, $filename.'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
