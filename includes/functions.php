<?php
/**
 * 共用工具函數。
 * 由各頁面在需要時 require 引入。
 */

/**
 * 解析 target_club_ids 字串（逗號分隔的社團 ID）為整數陣列。
 * 用於 form_type = 'club_only' 的表單權限檢查。
 */
function parse_target_clubs($value)
{
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $items = array_map('trim', explode(',', $value));
    $items = array_values(array_filter($items, 'strlen'));
    return array_values(array_unique(array_map('intval', $items)));
}
