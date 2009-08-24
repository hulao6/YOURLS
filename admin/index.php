<?php
// Require Files
require_once( dirname(dirname(__FILE__)).'/includes/config.php' );
if (defined('YOURLS_PRIVATE') && YOURLS_PRIVATE == true)
	require_once( dirname(dirname(__FILE__)).'/includes/auth.php' );

// Connect To Database
$db = yourls_db_connect();

// Variables
$table_url = YOURLS_DB_TABLE_URL;

$where = $search_display = $search_text = $search_url = $url = $keyword = '';
$search_in_text = 'URL';
$search_in_sql = 'url';
$sort_by_text = 'ID';
$sort_by_sql = 'id';
$sort_order_text = 'Descending Order';
$sort_order_sql = 'desc';
$page = ( isset( $_GET['page'] ) ? intval($_GET['page']) : 1 );
$search = ( isset( $_GET['s_search'] ) ? mysql_real_escape_string(trim($_GET['s_search'])) : '' );
$perpage = ( isset( $_GET['perpage'] ) && intval( $_GET['perpage'] ) ? intval($_GET['perpage']) : 10 );
$link_limit = ( isset( $_GET['link_limit'] ) && intval($_GET['link_limit']) ? intval($_GET['link_limit']) : '' );
if ( $link_limit != '' ) {
	$link_filter = ( isset( $_GET['link_filter'] ) && $_GET['link_filter'] == 'more' ? 'more' : 'less' ) ;
	$link_moreless = ( $link_filter == 'more' ? '>=' : '<=' );
	$where = " AND clicks $link_moreless $link_limit";
} else {
	$link_filter = '';
}
$base_page = YOURLS_SITE . '/admin/index.php';

// Searching
if(!empty($search) && !empty($_GET['s_in'])) {
	switch($_GET['s_in']) {
		case 'id':
			$search_in_text = 'ID';
			$search_in_sql = 'id';
			break;
		case 'url':
			$search_in_text = 'URL';
			$search_in_sql = 'url';
			break;
		case 'ip':
			$search_in_text = 'IP Address';
			$search_in_sql = 'ip';
			break;
	}
	$search_text = stripslashes($search);
	$search_display = "Searching for <strong>$search_text</strong> in <strong>$search_in_text</strong>. ";
	$search_url = "&amp;s_search=$search_text &amp;s_in=$search_in_sql";
	$search = str_replace('*', '%', '*'.$search.'*');
	$where .= " AND $search_in_sql LIKE ('$search')";
}

// Sorting
if(!empty($_GET['s_by']) || !empty($_GET['s_order'])) {
	switch($_GET['s_by']) {
		case 'id':
			$sort_by_text = 'ID';
			$sort_by_sql = 'id';
			break;
		case 'url':
			$sort_by_text = 'URL';
			$sort_by_sql = 'url';
			break;
		case 'timestamp':
			$sort_by_text = 'Date';
			$sort_by_sql = 'timestamp';
			break;
		case 'ip':
			$sort_by_text = 'IP Address';
			$sort_by_sql = 'ip';
			break;
		case 'clicks':
			$sort_by_text = 'Clicks';
			$sort_by_sql = 'clicks';
			break;
	}
	switch($_GET['s_order']) {
		case 'asc':
			$sort_order_text = 'Ascending Order';
			$sort_order_sql = 'asc';
			break;
		case 'desc':
			$sort_order_text = 'Descending Order';
			$sort_order_sql = 'desc';
			break;
	}
}

// Get URLs Count for current filter, total links in DB & total clicks
$total_items = $db->get_var("SELECT COUNT(id) FROM $table_url WHERE 1=1 $where");
$totals = $db->get_row("SELECT COUNT(id) as c, SUM(clicks) as s FROM $table_url WHERE 1=1");

// This is a bookmarklet
if ( isset( $_GET['u'] ) ) {
	$is_bookmark = true;

	$url = $_GET['u'];
	$keyword = ( isset( $_GET['k'] ) ? $_GET['k'] : '' );
	$return = yourls_add_new_link( $url, $keyword, $db );
	
	// If fails because keyword already exist, retry with no keyword
	if ( $return['status'] == 'fail' && $return['code'] == 'error:keyword' ) {
		$msg = $return['message'];
		$return = yourls_add_new_link( $url, '', $db );
		$return['message'] .= ' ('.$msg.')';
	}
	
	$s_url = stripslashes( $url );
	$where = " AND url LIKE '$s_url' ";
	
	$page = $total_pages = $perpage = 1;
	$offset = 0;
	
	$text = ( isset( $_GET['s'] ) ? stripslashes( $_GET['s'] ) : '' );
	$title = ( isset( $_GET['t'] ) ? stripslashes( $_GET['t'] ) : '' );

// This is not a bookmarklet
} else {
	$is_bookmark = false;
	
	// Checking $page, $offset, $perpage
	if(empty($page) || $page == 0) { $page = 1; }
	if(empty($offset)) { $offset = 0; }
	if(empty($perpage) || $perpage == 0) { $perpage = 50; }

	// Determine $offset
	$offset = ($page-1) * $perpage;

	// Determine Max Number Of Items To Display On Page
	if(($offset + $perpage) > $total_items) { 
		$max_on_page = $total_items; 
	} else { 
		$max_on_page = ($offset + $perpage); 
	}

	// Determine Number Of Items To Display On Page
	if (($offset + 1) > ($total_items)) { 
		$display_on_page = $total_items; 
	} else { 
		$display_on_page = ($offset + 1); 
	}

	// Determing Total Amount Of Pages
	$total_pages = ceil($total_items / $perpage);

}


// Begin output of the page
$context = ( $is_bookmark ? 'bookmark' : 'index' );
yourls_html_head( $context );
?>
	<h1>
		<a href="<?php echo $base_page; ?>" title="YOURLS"><span>YOURLS</span>: <span>Y</span>our <span>O</span>wn <span>URL</span> <span>S</span>hortener<br/>
		<img src="<?php echo YOURLS_SITE; ?>/images/yourls-logo.png" alt="YOURLS" title="YOURLS" style="border: 0px;" /></a>
	</h1>
	<?php if ( defined('YOURLS_PRIVATE') && YOURLS_PRIVATE == true ) { ?>
		<p>Your are logged in as: <strong><?php echo YOURLS_USER; ?></strong>. <a href="?mode=logout" title="Logout">Logout</a>. Check the <a href="tools.php">Tools</a>.</p>
	<?php } ?>
	<p><?php if ( !$is_bookmark ) {
	?>
	Display <strong><?php echo $display_on_page; ?></strong> to <strong class='increment'><?php echo $max_on_page; ?></strong> of <strong class='increment'><?php echo $total_items; ?></strong> URLs.
		<?php echo $search_display; ?>
	<?php } ?>
		Overall, tracking <strong class='increment'><?php echo number_format($totals->c); ?></strong> links, <strong><?php echo number_format($totals->s); ?></strong> clicks, and counting!
	</p>

	<?php yourls_html_addnew(); ?>
	
	<?php if ( $is_bookmark ) {
		echo '<h2 class="bookmark_result">' . $return['message'] . '</h2>';
	
	} ?>
	
	<table id="tblUrl" class="tblSorter" cellpadding="0" cellspacing="1">
		<thead>
			<tr>
				<th>Link&nbsp;ID&nbsp;&nbsp;</th>
				<th>Original URL</th>
				<th>Short URL</th>
				<th>Date</th>
				<th>IP</th>
				<th>Clicks&nbsp;&nbsp;</th>
				<th>Actions</th>
			</tr>
		</thead>

		<?php
		if ( !$is_bookmark ) {
			$params = array(
				'search_text'    => $search_text,
				'search_in_sql'  => $search_in_sql,
				'sort_by_sql'    => $sort_by_sql,
				'sort_order_sql' => $sort_order_sql,
				'page'           => $page,
				'perpage'        => $perpage,
				'link_filter'    => $link_filter,
				'link_limit'     => $link_limit,
				'total_pages'    => $total_pages,
				'base_page'      => $base_page,
				'search_url'     => $search_url,
			);
			yourls_html_tfooter( $params );
		}
		?>

		<tbody>
			<?php
			// Main Query
			$url_results = $db->get_results("SELECT * FROM $table_url WHERE 1=1 $where ORDER BY $sort_by_sql $sort_order_sql LIMIT $offset, $perpage;");
			if($url_results) {
				foreach( $url_results as $url_result ) {
					$base36 = yourls_int2string($url_result->id);
					$timestamp = strtotime($url_result->timestamp);
					$id = ($url_result->id);
					$url = stripslashes($url_result->url);
					$ip = $url_result->ip;
					$clicks = $url_result->clicks;

					echo yourls_table_add_row($id, $base36, $url, $ip, $clicks, $timestamp );
				}
			} else {
				echo '<tr class="nourl_found"><td colspan="7">No URL Found</td></tr>';
			}
			?>
		</tbody>
	</table>
	
	<?php if ( $is_bookmark )
		yourls_share_box( $url, $return['shorturl'], $title, $text );
	?>
	
<?php yourls_html_footer(); ?>