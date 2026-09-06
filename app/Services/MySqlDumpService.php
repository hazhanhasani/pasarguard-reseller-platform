<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MySqlDumpService
{
    /** @param string[] $excludedTables */
    public function dump(string $path, array $excludedTables = []): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') throw new RuntimeException('mysql_backup_required');
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('backup_temp_directory_failed');
        $handle = fopen($path, 'xb');
        if ($handle === false) throw new RuntimeException('backup_dump_open_failed');
        @chmod($path, 0600);

        try {
            $pdo = DB::connection()->getPdo();
            fwrite($handle, "-- PasarGuard Reseller Platform database backup\n");
            fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            $rows = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            foreach ($rows as $row) {
                $values = array_values((array) $row);
                $table = (string) ($values[0] ?? '');
                if ($table === '' || in_array($table, $excludedTables, true)) continue;
                $identifier = $this->identifier($table);
                $createRow = DB::selectOne('SHOW CREATE TABLE '.$identifier);
                if (!$createRow) throw new RuntimeException('backup_create_table_failed');
                $createValues = array_values((array) $createRow);
                $createSql = (string) ($createValues[1] ?? '');
                if ($createSql === '') throw new RuntimeException('backup_create_table_missing');
                $createSql = preg_replace('/[\r\n\t]+/', ' ', $createSql) ?: $createSql;
                fwrite($handle, "DROP TABLE IF EXISTS {$identifier};\n{$createSql};\n");

                $statement = $pdo->query('SELECT * FROM '.$identifier);
                if ($statement === false) throw new RuntimeException('backup_table_read_failed');
                while (($data = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
                    $columns = array_map(fn (string $column) => $this->identifier($column), array_keys($data));
                    $encoded = array_map(fn ($value) => $this->literal($value), array_values($data));
                    fwrite($handle, 'INSERT INTO '.$identifier.' ('.implode(',', $columns).') VALUES ('.implode(',', $encoded).");\n");
                }
                $statement->closeCursor();
            }
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }

    private function identifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    private function literal(mixed $value): string
    {
        if ($value === null) return 'NULL';
        if (is_int($value) || is_float($value)) return (string) $value;
        if (is_bool($value)) return $value ? '1' : '0';
        return "X'".bin2hex((string) $value)."'";
    }
}
