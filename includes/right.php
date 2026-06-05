<?php
// ─── 集中式側邊欄選單設定 ───
$all_sections = [
	'forms' => [
		'title' => '表單功能',
		'note' => '表單管理與填寫',
		'links' => [
			['label' => '表單中心', 'href' => 'forms_index.php', 'description' => '表單總覽首頁'],
			['label' => '建立表單', 'href' => 'create.php', 'description' => '建立新表單'],
			['label' => '我的表單', 'href' => 'my_forms.php', 'description' => '查看我建立的表單'],
			['label' => '表單列表', 'href' => 'list.php', 'description' => '所有可填寫的表單']
		]
	],
	'clubs' => [
		'title' => '社團中心功能',
		'note' => '左側快速切換社團相關頁面。',
		'links' => [
			['label' => '社團中心', 'href' => 'clubs_index.php', 'description' => '社團中心首頁'],
			['label' => '建立社團', 'href' => 'create.php', 'description' => '建立社團'],
			['label' => '社團資訊', 'href' => 'manage.php', 'description' => '社團資訊'],
			['label' => '社團設定', 'href' => 'setting.php', 'description' => '社團設定']
		]
	],
	'admin' => [
		'title' => '管理員功能',
		'note' => '系統管理工具',
		'links' => [
			['label' => '管理後台', 'href' => 'admin_index.php', 'description' => '管理員首頁'],
			['label' => '使用者管理', 'href' => 'user_CRUD.php', 'description' => '管理使用者'],
			['label' => '表單管理', 'href' => 'forms_CRUD.php', 'description' => '管理所有表單'],
			['label' => 'SQL 檢視', 'href' => 'sql_view.php', 'description' => '資料庫結構'],
			['label' => 'SQL 重置', 'href' => 'sql_reset.php', 'description' => '重新匯入 SQL'],
			['label' => '簡易測試', 'href' => 'test.php', 'description' => '簡易測試頁面']
		]
	],
	'users' => [
		'title' => '個人中心',
		'note' => '個人設定與管理',
		'links' => [
			['label' => '個人資料', 'href' => 'user_index.php', 'description' => '個人資訊'],
			['label' => '帳號設定', 'href' => 'setting.php', 'description' => '修改個人資料']
		]
	]
];

// ─── 自動判斷所在區塊 ───
$current_script = $_SERVER['SCRIPT_NAME'] ?? '';
$current_dir = dirname($current_script);
$current_dir = trim(str_replace('\\', '/', $current_dir), '/');
$dir_parts = explode('/', $current_dir);
$section_key = end($dir_parts);

// 允許頁面手動指定 $section 覆蓋自動偵測
if (!isset($section) || !array_key_exists($section, $all_sections)) {
	$section = isset($all_sections[$section_key]) ? $section_key : 'default';
}

$section_config = $all_sections[$section];
$right_title = $section_config['title'];
$right_note = $section_config['note'];
$right_links = $section_config['links'];

$can_manage = !empty($is_admin_user) || !empty($is_club_officer);
if ($section_key === 'clubs' && !$can_manage) {
	$right_links = array_values(array_filter($right_links, function ($link) {
		return ($link['label'] ?? '') !== '社團設定';
	}));
}

// ─── 路徑基底（預設為當前目錄） ───
if (!isset($right_base_url)) {
	$right_base_url = './';
}
if ($right_base_url !== '' && substr($right_base_url, -1) !== '/') {
	$right_base_url .= '/';
}

// ─── 輔助函數 ───
if (!function_exists('right_build_href')) {
	function right_build_href($base_url, $href)
	{
		if ($href === '' || $href === null) {
			return $base_url;
		}
		if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $href)) {
			return $href;
		}
		return $base_url . ltrim($href, '/');
	}
}
?>
<style>
#global-left-sidebar {
	position: fixed;
	left: 0;
	top: 0;
	height: 100vh;
	width: 260px;
	padding: 18px;
	overflow: auto;
	z-index: 999;
	border-radius: 0;
	background: #fff;
	box-shadow: 1px 0 0 rgba(0,0,0,0.04);
}
#global-left-sidebar h2 { margin-top: 0; }
#global-left-sidebar > .muted { margin-top: 8px; }
.sidebar-links { display: grid; gap: 10px; margin-top: 14px; }
.sidebar-link { padding: 12px; }
.sidebar-link.active { border-color: #8bc9b4; background: #eef7f3; }
.sidebar-link p.muted { margin-top: 4px; }
@media (max-width: 900px) {
	#global-left-sidebar {
		display: none;
	}
}
</style>
<aside id="global-left-sidebar" class="panel">
	<h2><?php echo htmlspecialchars($right_title); ?></h2>
	<?php if ($right_note !== '') : ?>
		<p class="muted"><?php echo htmlspecialchars($right_note); ?></p>
	<?php endif; ?>
	<div class="sidebar-links">
		<?php foreach ($right_links as $item) : ?>
			<?php
				$href = isset($item['href']) ? (string) $item['href'] : '';
				$label = isset($item['label']) ? (string) $item['label'] : $href;
				$description = isset($item['description']) ? (string) $item['description'] : '';
				$active = false;
				$target = right_build_href($right_base_url, $href);
				if ($href !== '' && $current_script !== '') {
					$normalized = '/' . ltrim($href, '/');
					$active = (substr($current_script, -strlen($normalized)) === $normalized);
				}
			?>
			<a
				href="<?php echo htmlspecialchars($target); ?>"
				class="panel sidebar-link<?php echo $active ? ' active' : ''; ?>"
			>
				<strong><?php echo htmlspecialchars($label); ?></strong>
				<?php if ($description !== '') : ?>
					<p class="muted"><?php echo htmlspecialchars($description); ?></p>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
</aside>

<script>
(function(){
	var sidebar = document.getElementById('global-left-sidebar');
	if (!sidebar) return;
	var lastWide = window.innerWidth >= 900;
	var w = function(){
		var sw = sidebar.offsetWidth || 260;
		var wide = window.innerWidth >= 900;
		if (wide) {
			sidebar.style.display = '';
			document.documentElement.style.paddingLeft = sw + 'px';
			document.body.style.paddingLeft = '0';
		} else {
			sidebar.style.display = 'none';
			document.documentElement.style.paddingLeft = '0';
			document.body.style.paddingLeft = '0';
		}
		lastWide = wide;
	};
	w();
	window.addEventListener('resize', function(){
		w();
	});
})();
</script>
