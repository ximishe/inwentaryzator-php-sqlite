<?php
declare(strict_types=1);

function get_app_fields(): array {
    $fields = db()->query("SELECT * FROM app_fields ORDER BY order_form ASC")->fetchAll();
    return $fields;
}

function get_main_fields(array $fields): array {
    $res = array_filter($fields, fn($f) => $f['show_in_main'] == 1);
    usort($res, fn($a, $b) => $a['order_main'] <=> $b['order_main']);
    return $res;
}

function get_detail_fields(array $fields): array {
    $res = array_filter($fields, fn($f) => $f['show_in_details'] == 1);
    usort($res, fn($a, $b) => $a['order_details'] <=> $b['order_details']);
    return $res;
}

function render_field_value(array $field, array $item, array $custom_fields): string {
    $val = '';
    
    // Get raw value
    if ($field['source_type'] === 'native') {
        $k = $field['field_key'];
        
        // Special rendering for specific native fields
        if ($k === 'status') {
            if($item['status'] == 'ACTIVE') return '<span class="badge bg-success">ACTIVE</span>';
            if($item['status'] == 'WAREHOUSE') return '<span class="badge bg-secondary">WAREHOUSE</span>';
            if($item['status'] == 'REPAIR') return '<span class="badge bg-warning text-dark">REPAIR</span>';
            return '<span class="badge bg-dark">'.htmlspecialchars($item['status']).'</span>';
        }
        if ($k === 'user_id') {
            return $item['user_id'] ? htmlspecialchars($item['first_name'] . ' ' . $item['last_name'] . ' (' . $item['u_login'] . ')') : '<span class="text-muted fst-italic">Magazyn / Brak</span>';
        }
        if ($k === 'type_id') {
            return htmlspecialchars((string)$item['type_name']) ?: '<span class="text-muted fst-italic">Brak</span>';
        }
        if ($k === 'room') {
            return '<span class="badge bg-info text-dark">'.htmlspecialchars((string)$item['room'] ?: 'Brak').'</span>';
        }
        if ($k === 'purchase_price' && $item['purchase_price']) {
            return htmlspecialchars((string)$item['purchase_price']).' PLN';
        }
        
        $val = (string)($item[$k] ?? '');
    } else {
        // Custom field
        $k = $field['field_key']; // e.g. 'custom_12' but we don't have this structure yet.
        // Wait, custom fields in device_field_value are accessed by name currently
        $found = false;
        foreach ($custom_fields as $cf) {
            if ($cf['name'] === $field['label']) { // Temporary mapping
                $val = $cf['value'];
                $found = true;
                break;
            }
        }
        if (!$found) return '<span class="text-muted fst-italic">-</span>';
    }

    if ($val === '') return '<span class="text-muted fst-italic">Brak</span>';
    
    if ($field['field_key'] === 'asset_number') {
        return '<strong>'.htmlspecialchars($val).'</strong>';
    }
    if ($field['field_key'] === 'serial_number') {
        return '<code class="text-light">'.htmlspecialchars($val).'</code>';
    }

    return htmlspecialchars($val);
}
?>