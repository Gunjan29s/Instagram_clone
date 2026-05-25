<?php
function admin_filter_by_date(array $rows, string $date, string $column = 'created_at'): array {
    $date = trim($date);
    if ($date === '') {
        return $rows;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        return $rows;
    }

    return array_values(array_filter($rows, function($row) use ($date, $column) {
        if (empty($row[$column])) {
            return false;
        }

        $rowTime = strtotime((string) $row[$column]);
        return $rowTime && date('Y-m-d', $rowTime) === $date;
    }));
}
