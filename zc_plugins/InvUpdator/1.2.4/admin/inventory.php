<?php
    /*  portions copyright by... zen-cart.com

        developed and brought to you by proseLA
        https://mxworks.cc

        released under GPU
        https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

        07/2022  project: inventory v1.2.5 file: inventory.php
    */

    require('includes/application_top.php');

    require_once(DIR_WS_CLASSES . 'currencies.php');
    $currencies = new currencies();

    $cid = 0;
    $cid_params = '';
    if (isset($_GET['cid'])) {
        $cid = $_GET['cid'];
        $cid_params .= '&cid=' . $cid;
    }

    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : '0';
    $active = isset($_GET['active']) ? $_GET['active'] : '0';

    if ($action == 'tg_stat' && isset($_GET['pid']) && is_numeric($_GET['pid'])) {
        $rs_stat = $db->Execute("select products_status from " . TABLE_PRODUCTS . " where products_id=" . intval($_GET['pid']) . " LIMIT 1");
        $db->Execute("update " . TABLE_PRODUCTS . " set products_status = '" . ($rs_stat->fields['products_status'] == '1' ? '0' : '1') . "' where products_id=" . intval($_GET['pid']) . " LIMIT 1");
        zen_redirect(zen_href_link(
            FILENAME_INVENTORY,
            'sort=' . $sort . '&active=' . $active . $cid_params,
            'SSL'
        ));
    }

    $update_qty = 0;

    if (isset($_POST['update_']) && is_array($_POST['new_qty']) && is_array($_POST['old_qty'])) {
        $old_qty = $_POST['old_qty'];
        foreach ($_POST['new_qty'] as $item => $qty) {
            if (array_key_exists($item, $old_qty) && $old_qty[$item] !== $qty) {
                if (is_numeric($qty) and is_numeric($item)) {
                    $db->Execute("update " . TABLE_PRODUCTS . " set products_quantity = '" . intval($qty) . "' where products_id = '" . intval($item) . "' and products_quantity = '" . intval($old_qty[$item]) . "' limit 1");
                    if ($db->affectedRows() == 1) {
                        $update_qty++;
                    }
                }
            } else {
                //echo "old: $old_qty[$item] , new: $qty <br />";
            }
        }
    }

    $updated_count_price = 0;

    if (isset($_POST['update_']) && is_array($_POST['new_price']) && is_array($_POST['old_price'])) {
        $old_price = $_POST['old_price'];
        foreach ($_POST['new_price'] as $item => $price) {
            if (array_key_exists($item, $old_price) && $old_price[$item] !== $price) {
                if (is_numeric($price) and is_numeric($item)) {
                    $db->Execute("update " . TABLE_PRODUCTS . " set products_price = '" . convertToFloat($price) . "' where products_id = '" . intval($item) . "' and products_price = '" . (float)($old_price[$item]) . "' limit 1");
                    if ($db->affectedRows() == 1) {
                        $updated_count_price++;
                    }
                }
            } else {
                //echo "old: $old_qty[$item] , new: $qty <br />";
            }
        }
    }


    $order_by = " ";
    switch ($sort) {
        case (0):
            $order_by = " ORDER BY p.products_sort_order, pd.products_name";
            break;
        case (1):
            $order_by = " ORDER BY pd.products_name";
            break;
        case (2):
        $order_by = " ORDER BY p.products_model";
        break;
        case (3):
        $order_by = " ORDER BY p.products_quantity, pd.products_name";
        break;
        case (4):
        $order_by = " ORDER BY p.products_quantity DESC, pd.products_name";
        break;
        case (5):
        $order_by = " ORDER BY p.products_price_sorter, pd.products_name";
        break;
        case (6):
        $order_by = " ORDER BY p.products_price_sorter DESC, pd.products_name";
        break;
    }

    $a_field = '';
    switch ($active) {
        case '1':
            $a_field = '';
            break;

        case '0':
        default:
            $a_field = ' and p.products_status > 0';
            break;
    }

    $categories = $db->Execute("select c.categories_id, cd.categories_name, c.sort_order "
        . " from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd "
        . " where c.categories_id = cd.categories_id and c.parent_id = " . $cid . " and cd.language_id = '" . (int)$_SESSION['languages_id'] . "'"
        . " order by sort_order ");

    if ($categories->count() == 0) {
        $categories = $db->Execute("select c.categories_id, cd.categories_name, c.sort_order "
            . " from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd "
            . " where c.categories_id = cd.categories_id and c.parent_id = 0 and cd.language_id = '" . (int)$_SESSION['languages_id'] . "'"
            . " order by sort_order ");
    }

    if ($cid <> 0) {
        $prod_sql = "select p.products_id, p.products_type, p.products_date_available, p.products_status,p.products_quantity, 
	p.products_price, p.products_model, pd.products_name,  pc.categories_id, p.product_is_call, p.master_categories_id as catId "
            . " from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " pc "
            . " left join " . TABLE_CATEGORIES . " ct on ct.categories_id = pc.categories_id"
            . " where p.products_id = pd.products_id and p.products_id = pc.products_id and pd.language_id = '" . (int)$_SESSION['languages_id'] . "'"
            . " and (pc.categories_id = " . intval($cid) . " or ct.parent_id = " . intval($cid) . ")" . $a_field;
    } else {
        $prod_sql = "select p.products_id, p.products_type, p.products_date_available, p.products_status,p.products_quantity, 
	p.products_price, p.products_model, pd.products_name, p.product_is_call, p.master_categories_id as catId "
            . " from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd  "
            . " where p.products_id = pd.products_id and pd.language_id = '" . (int)$_SESSION['languages_id'] . "'" . $a_field;
    }

    $prod_sql .= $order_by;

    $query_num_rows = 0;
    $pageNo = intval((isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1));
    $products_page = new splitPageResults($pageNo, MAX_DISPLAY_RESULTS_CATEGORIES, $prod_sql, $query_num_rows);
    $prod_list = $db->Execute($prod_sql);

    $query_string = '';
    foreach ($_GET as $k => $v) {
        if ($k != 'page') {
            $query_string .= $k . '=' . $v . '&';
        }
    }

    $pager = $products_page->display_count(
        $query_num_rows,
        MAX_DISPLAY_RESULTS_CATEGORIES,
        $pageNo,
        TEXT_DISPLAY_NUMBER_OF_PRODUCTS
    );
    $pager .= '&nbsp;-&nbsp;';
    $pager .= $products_page->display_links(
        $query_num_rows,
        MAX_DISPLAY_RESULTS_CATEGORIES,
        MAX_DISPLAY_PAGE_LINKS,
        $pageNo,
        $query_string
    );

    $categories_products_sort_order_array = [
        ['id' => '0', 'text' => TEXT_SORT_PRODUCTS_SORT_ORDER_PRODUCTS_NAME],
        ['id' => '1', 'text' => TEXT_SORT_PRODUCTS_NAME],
        ['id' => '2', 'text' => TEXT_SORT_PRODUCTS_MODEL],
        ['id' => '3', 'text' => TEXT_SORT_PRODUCTS_QUANTITY],
        ['id' => '4', 'text' => TEXT_SORT_PRODUCTS_QUANTITY_DESC],
        ['id' => '5', 'text' => TEXT_SORT_PRODUCTS_PRICE],
        ['id' => '6', 'text' => TEXT_SORT_PRODUCTS_PRICE_DESC],
    ];
    ?>
    <!doctype html>
    <html <?= HTML_PARAMS; ?>>
    <head>
        <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
        <script src="includes/general.js"></script>
    </head>
    <body>
    <!-- header //-->
    <?php require(DIR_WS_INCLUDES . 'header.php'); ?>
    <!-- header_eof //-->

    <!-- body //-->
    <div class="container-fluid" id="pageWrapper">
        <div class="col-md-11 alert-box alert alert-info">
            <h3><?= INVENTORY_PAGE; ?></h3>
            <?php
                    $parent_name = zen_get_category_name($cid, (int)$_SESSION['languages_id']);
    if ($cid > 0) {
        ?>
                    <h3><?= $parent_name; ?></h3>
                <?php
    } ?>
        </div>
        <div class="col-md-11">
            <?php if (isset($update_qty) && $update_qty > 0) { ?>
                <div class="alert alert-success fade in alert-dismissible show">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="center"><?= $update_qty . PRODUCTS_UPDATED; ?></h4>
                </div>
            <?php } ?>

            <?php if (isset($updated_count_price) && $updated_count_price > 0) { ?>
                <div class="alert alert-success fade in alert-dismissible show">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="center"><?= $updated_count_price . PRODUCTS_PRICE_UPDATED; ?></h4>
                </div>
            <?php } ?>

            <div>
                <?= zen_draw_form('selection', FILENAME_INVENTORY, 'cid=' . $cid . '&cmd=' . FILENAME_INVENTORY, 'get', 'class="form-horizontal"'); ?>

                <?= zen_draw_label(CATEGORY_SELECTOR, 'cid', 'class="col-sm-6 col-md-4 control-label"'); ?>
                <div class="col-sm-6 col-md-8">
                    <?php
            echo zen_draw_pull_down_menu('cid', zen_get_category_tree(), $cid, 'onChange="this.form.submit()" class="form-control" id="cid"');
    ?>
                </div>
                <?= zen_draw_label(
        TEXT_CATEGORIES_PRODUCTS_SORT_ORDER_INFO,
        'sort',
        'class="col-sm-6 col-md-4 control-label"'
    ); ?>
                <div class="col-sm-6 col-md-8">
                    <?= zen_draw_pull_down_menu(
                        'sort',
                        $categories_products_sort_order_array,
                        $sort,
                        'onchange="this.form.submit();" class="form-control" id="sort"'
                    ); ?>
                </div>
                <?= zen_draw_label(ACTIVE_STATUS, 'active', 'class="col-sm-6 col-md-4 control-label"'); ?>
                <div class="col-sm-6 col-md-8">
                    <select id="active" name="active" onChange="this.form.submit()" class="form-control">
                        <option value="0" <?= ($active == '0' ? 'SELECTED' : ''); ?>>Only Active
                        <option value="1" <?= ($active == '1' ? 'SELECTED' : ''); ?>>All Products
                    </select>&nbsp;
                </div>
                </form>
            </div>
            <hr/>
            <div>

                <div class="row">
                    <div class="configurationColumnLeft">

                        <table class="table table-striped table-hover table-bordered top-pager-table">
                        <tr>
                            <td class="text-right"><?= $pager; ?></td>
                        </tr>
                        </table>
                        <?= zen_draw_form('inventory_update', FILENAME_INVENTORY, 'sort=' . $sort . '&active=' . $active . '&page=' . ($_GET['page'] ?? 1) . $cid_params); ?>
                            <table class="table table-striped table-hover table-bordered">
                                <thead>

                                <tr class="dataTableHeadingRow">
                                    <th class="dataTableHeadingContent"><?= TABLE_HEADING_ID; ?></th>
                                    <th class="dataTableHeadingContent text-center"><?= TABLE_HEADING_MODEL; ?></th>
                                    <th class="dataTableHeadingContent"><?= TABLE_HEADING_NAME; ?></th>
                                    <th class="dataTableHeadingContent text-center"><?= TABLE_HEADING_STATUS; ?></th>
                                    <th class="dataTableHeadingContent text-right"><?= TABLE_HEADING_QTY; ?></th>
                                    <th class="dataTableHeadingContent text-right"><?= TABLE_HEADING_BASE_PRICE; ?></th>
                                    <th class="dataTableHeadingContent text-right"><?= TABLE_HEADING_DISPLAY_PRICE; ?></th>
                                </tr>
                                </thead>


                                <?php while ($prod_list && !$prod_list->EOF) {
                                    $type_handler = $zc_products->get_admin_handler($prod_list->fields['products_type']);
                                    if (isset($bad_sku) && array_key_exists(
                                        $prod_list->fields['products_id'],
                                        $bad_sku
                                    )) {
                                        $color = ' bgcolor="red" ';
                                    } else {
                                        $color = '';
                                    } ?>

                                    <tr class="dataTableRow" onmouseover="rowOverEffect(this)"
                                        onmouseout="rowOutEffect(this)">
                                        <td <?= $color; ?> class="dataTableContent"
                                                           onclick="document.location.href='<?= zen_href_link(
                                        $type_handler,
                                        'page=1' . '&product_type=' . $prod_list->fields['products_type'] . '&pID=' . $prod_list->fields['products_id'] . '&action=new_product'
                                    ); ?> '"><?= $prod_list->fields['products_id']; ?></td>
                                        <td class="dataTableContent text-center">
                                            <?= $prod_list->fields['products_model']; ?>
                                        </td>
                                        <td class="dataTableContent"
                                            onclick="document.location.href='<?= zen_href_link(
                                                                   FILENAME_PRODUCT,
                                                                   'product_type=' . $prod_list->fields['products_type'] . '&pID=' . $prod_list->fields['products_id'] . '&cPath=' . $prod_list->fields['catId'] . '&action=new_product'
                                                               ); ?> '">
                                            <?php
                                                echo zen_get_products_name($prod_list->fields['products_id']); ?>
                                        </td>
                                        <td class="text-center">

                                            <a href="<?= zen_href_link(
                                                    FILENAME_INVENTORY,
                                                    'action=tg_stat' . '&pid=' . $prod_list->fields['products_id'] . $cid_params . '&sort=' . $sort . '&active=' . $active,
                                                    'SSL'
                                                ); ?>">
                                                <?= ($prod_list->fields['products_status']
                                                    ? zen_image(
                                                        DIR_WS_IMAGES . 'icon_green_on.gif',
                                                        IMAGE_ICON_STATUS_ON
                                                    )
                                                    : zen_image(
                                                        DIR_WS_IMAGES . 'icon_red_on.gif',
                                                        IMAGE_ICON_STATUS_OFF
                                                    )) ?>
                                            </a>
                                        </td>
                                        <td class="dataTableContent text-right">
                                            <input type="text"
                                                   name="new_qty[<?= $prod_list->fields['products_id']; ?>]"
                                                   value="<?= $prod_list->fields['products_quantity']; ?>" size="5"/>
                                            <input type="hidden"
                                                   name="old_qty[<?= $prod_list->fields['products_id']; ?>]"
                                                   value="<?= $prod_list->fields['products_quantity']; ?>"/>
                                        </td>

                                        <td class="dataTableContent text-right">
                                            <input type="text"
                                                   name="new_price[<?= $prod_list->fields['products_id']; ?>]"
                                                   value="<?= $prod_list->fields['products_price']; ?>" size="7"/>
                                            <input type="hidden"
                                                   name="old_price[<?= $prod_list->fields['products_id']; ?>]"
                                                   value="<?= $prod_list->fields['products_price']; ?>"/>
                                        </td>
                                        <td class="dataTableContent text-right">
                                            &nbsp;<?= zen_get_products_display_price((int)$prod_list->fields['products_id']); ?></td>
                                    </tr>

                                    <?php $prod_list->MoveNext();
                                }
    ?>
                            </table>
                        <div>
      <span class="floatButton text-right">
    	<input type="reset" value="reset" class="btn btn-danger"/>
    	<input type="submit" name="update_" value="<?= BUTTON_UPDATE; ?>" class="btn btn-primary"/>
      </span>
                        </div>
                    </form>
                        <table class="table table-striped table-hover table-bordered bottom-pager-table">
                            <tr>
                                <td class="text-right"><?= $pager; ?></td>
                            </tr>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- body_eof //-->
    <!-- footer //-->
    <?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
    <!-- footer_eof //-->
    <br/>
    </body>
    </html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php');
