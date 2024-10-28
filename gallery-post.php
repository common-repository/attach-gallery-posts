<?php
/*
Plugin Name: Attach Gallery Post
Plugin URI: http://joeboydston.com/gallery-post/
Description: Plugin is for adding a gallery to a wordpress post
Author: Don Kukral
Version: 1.6
Author URI: http://d0nk.com
*/

define( 'GALLERY_POST_URL' , plugins_url(plugin_basename(dirname(__FILE__)).'/') );

add_action( 'wp_print_styles', 'add_gallery_post_admin_styles' );
add_action( 'admin_enqueue_scripts', 'add_gallery_post_admin_scripts' );

function add_gallery_post_admin_styles() {
	if (strstr($_SERVER['REQUEST_URI'], 'wp-admin/post-new.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/post.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit.php')) {
	    wp_enqueue_style( 'gallery_post-css', GALLERY_POST_URL.'css/gallery-post.css', false );
	    wp_enqueue_style( 'gallery_post-jquery-ui-css', GALLERY_POST_URL.'css/smoothness/jquery-ui-1.8.19.custom.css', false );
    }
}

function add_gallery_post_admin_scripts() {
	if (strstr($_SERVER['REQUEST_URI'], 'wp-admin/post-new.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/post.php') || strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit.php')) {
        wp_enqueue_script(array('jquery', 'jquery-ui-autocomplete'));
        //wp_enqueue_script('gallery-post-jquery-ui', GALLERY_POST_URL . 'js/jquery-ui-1.8.19.custom.min.js', array('jquery'), true);
    }
}

add_action('admin_menu', 'gallery_post_admin_menu');
add_action( 'admin_print_styles', 'add_gallery_post_admin_styles' );

function gallery_post_admin_menu() {
    add_options_page('Gallery Post', 'Gallery Post', 'administrator',
        'gallery-post', 'gallery_post_settings_page');
}

add_action('admin_init', 'gallery_post_custom_box', 1);
add_action('save_post', 'gallery_post_save_postdata');
add_action('publish_post', 'gallery_post_publish_post');
add_action('before_delete_post', 'gallery_post_delete_post');

add_filter("the_content", "gallery_post_content");

function gallery_post_content($content) {
    global $post;
    $gallery_post = get_post_meta($post->ID, 'gallery_post', True);
    if ($gallery_post > 0) {
        $gallery_post_content = do_shortcode('[gallery id="'. $gallery_post .'" captiontag="" itemtag="div" icontag="span" ]');
        $content .= '<div class="clear"></div><div id="gallery_post">' . $gallery_post_content . '</div>';
    }
    return $content;

}

function gallery_post_custom_box() {
    add_meta_box("gallery-post", __("Gallery Post", "gallery_post"), "gallery_post_innerbox_html", "post", "advanced");
}

function gallery_post_innerbox_html() {

    global $post, $wpdb;

    // Use nonce for verification
    wp_nonce_field( plugin_basename(__FILE__), 'gallery_post_noncename' );

    // get current gallery post
    $gallery_post = get_post_meta($post->ID, 'gallery_post', True);
    if ($gallery_post != '') {
        $gpost = get_post($gallery_post);
    }

    // The actual fields for data entry
    $category = get_category(get_option('gallery_post_category'));
    echo "<p><strong>Gallery Category:</strong> " . $category->name . "</p>";

    $posts = get_gallery_post_list($category);

    $upload_dir = wp_upload_dir();
    // $plugin_url = GALLERY_POST_URL . 'gallery-post-show.php';
    ?>
       	<style>
        	/*.ui-autocomplete-loading { background: white url('images/ui-anim_basic_16x16.gif') right center no-repeat; }*/
        	</style>
        	<script>
        	jQuery.noConflict();

            jQuery(document).ready(function($) {
                // var data = { action: 'gallery-lookup' }
        		jQuery( "#gallery_post_search" ).autocomplete({
        			source: ajaxurl + '?action=gallery_lookup',
        			minLength: 3,
        			select: function( event, ui ) {
        			    jQuery("#gallery_post_search").val(ui.item.id);
                        $.ajax({
                           url: ajaxurl + "?action=gallery_select&gallery=" + $("#gallery_post_search").val(),
                           success: function(value) {
                               $("#gallery_post_div").html(value);
                               $("select#gallery_post_select").val(ui.item.id);
                           }
                        });
        			},
        			close: function( event, ui ) {
        			     jQuery("#gallery_post_search").val("");
        			}
        		});
        	});
        	</script>

        	<p style="margin: 10px 0px; ">
        	Search For Gallery Post:
        	<input id="gallery_post_search" size="30" name="term"/>
        	</p>
    <?php
    echo '<p>Select the gallery to attach to the post: ';
    echo '<select id="gallery_post_select" name="gallery_post" class="combobox">';
    echo '<option value="-1">None</option>';
    if ($gpost) {
        echo '<option value="' . $gpost->ID . '">';
        echo $gpost->post_title;
        echo '</option>';
    }
    foreach ($posts as $category_post) {
        if (($gpost) && ($gpost->ID == $category_post['ID'])) { continue; }
        echo '<option value="'. $category_post['ID'] .'">';
        echo $category_post['post_title'];
        echo '</option>';
    }
    echo '</select></p>';
    echo '<div id="gallery_post_div"></div>';

?>

<script type="text/javascript">
/* <![CDATA[ */
    jQuery.noConflict();
    jQuery(document).ready(function($){
        var gallery = <?php echo intval($gallery_post); ?>;
        if (gallery) {
            var data = { gallery: gallery, action: 'gallery_select' }
            $.ajax({
                url: ajaxurl,
                data: data,
                success: function(value) {
                    $("#gallery_post_div").html(value);
                }
            })
            $("#gallery_post_select").val(<?php echo $gallery_post; ?>);
        }
        $("#gallery_post_select").change(function() {
            $.ajax({
               url: ajaxurl + "?action=gallery_select&gallery=" + $(this).val(),
               success: function(value) {
                   $("#gallery_post_div").html(value);
               }
            });
        });
    });
/* ]]> */
</script>
<?php
 }

function gallery_post_save_postdata ($post_id) {
    // verify this came from the our screen and with proper authorization,
    // because save_post can be triggered at other times
    if ( !wp_verify_nonce( $_POST['gallery_post_noncename'], plugin_basename(__FILE__) )) {
        return;
    }

    // verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
    // to do anything
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
        return;

    // Check permissions
    if ( 'page' == $_POST['post_type'] ) {
        if ( !current_user_can( 'edit_page', $post_id ) )
            return;
    } else {
        if ( !current_user_can( 'edit_post', $post_id ) )
            return;
    }

    // OK, we're authenticated: we need to find and save the data
    $gallery_post = $_POST['gallery_post'];
    if ($gallery_post > 1) {

        // check if it's changed
        $gp = get_post_meta($post_id, 'gallery_post', True);
        if (!$gp) {
            $p = get_post($post_id);
            if ($p->post_type != "post") { return ;}
        }
        if ($gp != $gallery_post) {
            // save the post data
            update_post_meta($post_id, 'gallery_post', $gallery_post);

            // update the thumbnail id
            $thumbnail_id = get_post_thumbnail_id($gallery_post);
            update_post_meta( $post_id, '_thumbnail_id', $thumbnail_id );

            // need to update gallery_post_list
            $category = get_category(get_option('gallery_post_category'));
            #update_gallery_post_list($category);
        }
        return;
    } else {
        // remove post meta data
        $gp = get_post_meta($post_id, 'gallery_post', True);
        if ($gp) {
            delete_post_meta($post_id, 'gallery_post');
            delete_post_meta($post_id, '_thumbnail_id');
            // need to update gallery_post_list
            $category = get_category(get_option('gallery_post_category'));
            #update_gallery_post_list($category);

        }
        $cat = get_post($post_id);
        $gallery_cat = get_category(get_option('gallery_post_category'));
    }


}

function gallery_post_publish_post($post_id) {
    $cat = get_the_category($post_id);
    $gallery_cat = get_category(get_option('gallery_post_category'));
    foreach ($cat as $c) {
        if ($c->term_id == $gallery_cat->term_id) {
            update_gallery_post_list($gallery_cat);
            return;
        }
    }
}

function gallery_post_delete_post($post_id) {
    $cat = get_the_category($post_id);
    $gallery_cat = get_category(get_option('gallery_post_category'));
    foreach ($cat as $c) {
        if ($c->term_id == $gallery_cat->term_id) {
            update_gallery_post_list($gallery_cat);
            return;
        }
    }
}

function gallery_post_settings_page() {
    global $wpdb;
?>
<div>
<h2 style="margin: 15px 0;">Gallery Post Options</h2>

<p style="margin: 10px 0">This is the category that empty gallery posts are stored in.</p>
<form method="post" action="options.php">
<?php wp_nonce_field('update-options'); ?>

<table width="510">
<tr valign="top">
<th width="120" scope="row">Gallery Category</th>
<td width="406">
    <?php
    wp_dropdown_categories(array('hide_empty' => 0, 'name' => 'gallery_post_category',
        'orderby' => 'name', 'selected' => get_option('gallery_post_category'), 'hierarchical' => true,
        'show_option_none' => __('None')));
    ?>
</td>
</tr>
<tr>
<td colspan="2">
<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="gallery_post_category" />

<p style="margin: 5px;">
<input type="submit" value="<?php _e('Save Changes') ?>" />
</p>
</td>
</tr>
</table>
</form>

<div id="gallery-post-reports">
<h2 style="margin: 15px 0;">Gallery Post Reports</h2>

<h3 style="margin: 10px 0;" id="gp_posts_without"><span>+</span> Posts with no gallery or featured image</h3>
<!-- <ul class="gp_posts_without"> -->
<?php
$gallery_post_category = get_option('gallery_post_category');

$sql = "SELECT l.ID, l.post_title, l.post_date, l.post_status, u.display_name FROM " . $wpdb->posts . " l, " . $wpdb->users . " u WHERE l.post_author=u.ID AND l.ID NOT IN (SELECT DISTINCT post_id FROM " . $wpdb->postmeta ." l WHERE meta_key = '_thumbnail_id' OR meta_key='gallery_post') AND l.ID NOT IN (SELECT object_id FROM " . $wpdb->term_relationships . " l WHERE l.term_taxonomy_id = " . $gallery_post_category . ") AND l.post_status='publish' and l.post_type='post' ORDER BY l.post_date DESC";
#print $sql;
$results = $wpdb->get_results($sql);

print '<table class="gp_posts_without post_list wp-list-table widefat fixed posts">';
print '<tr><th>Title</th><th>Author</th><th>Status</th><th>Date</th></tr>';
if ($results) {
    foreach ($results as $result) {
        print '<tr>';
        print edit_post_link($result->post_title, '<td>', '</td>', $result->ID);
        print '<td>' . $result->display_name . '</td>';
        print '<td>' . $result->post_status . '</td>';
        print '<td>' . $result->post_date . '</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="4">None found.</td></tr>';
}
?>
</table>
<!-- </ul> -->

<h3 style="margin: 10px 0;" id="gp_unattached"><span>+</span> Unattached Gallery Posts</h3>
<?php
$sql = "SELECT l.object_id, p.post_title, p.post_date, p.post_status, u.display_name FROM " . $wpdb->term_relationships . " l, ". $wpdb->posts . " p, " . $wpdb->users . " u WHERE p.post_author=u.ID AND l.object_id = p.ID AND l.object_id NOT IN (SELECT l.object_id FROM " . $wpdb->term_relationships . " l, " . $wpdb->postmeta . " p WHERE l.term_taxonomy_id = 61 and p.meta_key = 'gallery_post' and p.meta_value=l.object_id) AND l.term_taxonomy_id=61";
$results = $wpdb->get_results($sql);

print '<table class="gp_unattached post_list wp-list-table widefat fixed posts">';
print '<tr><th>Title</th><th>Author</th><th>Status</th><th>Date</th></tr>';
if ($results) {
    foreach ($results as $result) {
        print '<tr>';
        print edit_post_link($result->post_title, '<td>', '</td>', $result->object_id);
        print '<td>' . $result->display_name . '</td>';
        print '<td>' . $result->post_status . '</td>';
        print '<td>' . $result->post_date . '</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="4">None found.</td></tr>';
}
?>
</table>

<h3 style="margin: 10px 0;" id="gp_no_feature"><span>+</span> Gallery Posts w/o Featured Image</h3>
<?php
$sql = "SELECT l.object_id, p.post_title, p.post_date, p.post_status, u.display_name FROM " . $wpdb->term_relationships . " l, ". $wpdb->posts . " p, ". $wpdb->users . " u WHERE p.post_author = u.ID AND l.object_id = p.ID AND l.object_id NOT IN (SELECT l.object_id FROM " . $wpdb->term_relationships . " l, " . $wpdb->postmeta . " p WHERE l.term_taxonomy_id = 61 and p.meta_key = '_thumbnail_id' and p.post_id=l.object_id) AND l.term_taxonomy_id=61";

$results = $wpdb->get_results($sql);

print '<table class="gp_no_feature post_list wp-list-table widefat fixed posts">';
print '<tr><th>Title</th><th>Author</th><th>Status</th><th>Date</th></tr>';
if ($results) {
    foreach ($results as $result) {
        print '<tr>';
        print edit_post_link($result->post_title, '<td>', '</td>', $result->object_id);
        print '<td>' . $result->display_name . '</td>';
        print '<td>' . $result->post_status . '</td>';
        print '<td>' . $result->post_date . '</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="4"><strong>None found.</strong></td></tr>';
}
?>
</table>

</div>
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        jQuery("#gp_no_feature").click(function() {
            jQuery("table.gp_no_feature").toggle();
            if (jQuery("#gp_no_feature span").html() == "+") {
                jQuery("#gp_no_feature span").html("-");
            } else {
                jQuery("#gp_no_feature span").html("+");
            }
        });
        jQuery("#gp_unattached").click(function() {
            jQuery("table.gp_unattached").toggle();
            if (jQuery("#gp_unattached span").html() == "+") {
                jQuery("#gp_unattached span").html("-");
            } else {
                jQuery("#gp_unattached span").html("+");
            }
        });
        jQuery("#gp_posts_without").click(function() {
            jQuery("table.gp_posts_without").toggle();
            if (jQuery("#gp_posts_without span").html() == "+") {
                jQuery("#gp_posts_without span").html("-");
            } else {
                jQuery("#gp_posts_without span").html("+");
            }
        });
    });
</script>
<?php
}

function get_gallery_post_list($category) {
    // delete_option('gallery_post_list');
    $posts = get_option('gallery_post_list', false);
    if (false == $posts) {
        update_gallery_post_list($category);
    } else {
        if (gettype($posts) == 'string') {
            $posts = unserialize($posts);
        }
    }

    return $posts;
}

function update_gallery_post_list($category) {
    global $wpdb;

    $sql = " SELECT p.ID, p.post_title
    FROM " . $wpdb->prefix . "posts AS p
    INNER JOIN " . $wpdb->prefix . "term_relationships AS tr ON (p.ID = tr.object_id)
    WHERE 1=1
    AND ( tr.term_taxonomy_id = " . $category->term_taxonomy_id . ")
    AND p.post_type = 'post'
    AND (p.post_status = 'publish')
    GROUP BY p.ID
    ORDER BY p.post_date DESC LIMIT 250";
    $posts = $wpdb->get_results($sql, ARRAY_A);

    update_option('gallery_post_list', serialize($posts));
}

add_action('wp_ajax_gallery_lookup', 'gallery_lookup_callback');

function gallery_lookup_callback() {
    global $wpdb;

    if ($_GET['term']) {
        global $current_user;
        get_currentuserinfo();
        if (!$current_user->ID) { return; }
        $term = mysql_escape_string($_GET['term']);
        $category = get_category(get_option('gallery_post_category'));
        if ($category) {
            $sql = " SELECT p.ID, p.post_title
            FROM " . $wpdb->prefix . "posts AS p
            INNER JOIN " . $wpdb->prefix . "term_relationships AS tr ON (p.ID = tr.object_id)
            WHERE 1=1
            AND ( tr.term_taxonomy_id = " . $category->term_taxonomy_id . ")
            AND p.post_type = 'post'
            AND (p.post_status = 'publish')
            AND (p.post_title LIKE '%" . $term . "%')
            GROUP BY p.ID
            ORDER BY p.post_date DESC
            LIMIT 25";
            $posts = $wpdb->get_results($sql, ARRAY_A);
            $results = array();
            foreach ($posts as $p) {
                $row = array('id' => $p['ID'], 'label' => $p['post_title'], 'value' => $p['post_title']);
                array_push($results, $row);
            }
            print json_encode($results);
        }
    }
    die();
}

add_action('wp_ajax_gallery_select', 'gallery_select_callback');

function gallery_select_callback() {
    if (($_GET['gallery'] != "-1") && ($_GET['gallery'] != "")) {
    ?>
        <style type="text/css">
            #gallery-1 {
                margin: auto;
            }
            #gallery-1 .gallery-item {
                float: left;
                margin-top: 10px;
                text-align: center;
                width: 33%;
            }
            #gallery-1 img {
                border: 2px solid #cfcfcf;
            }
            #gallery-1 .gallery-caption {
                margin-left: 0;
            }
        </style>
        <div id="gallery-1" class="gallery galleryid-<?php echo $_GET['gallery'] ?> gallery-columns-3 gallery-size-thumbnail">
        <?php
        $gimages = get_children($_GET['gallery']);

        $i = 0;
        foreach ($gimages as $gimage) {
        ?>
        <span class="gallery-item">
        <span class="galery-icon">
        <a href="<?php echo admin_url(); ?>media.php?attachment_id=<?php echo $gimage->ID; ?>&action=edit">
        <img src="<?php echo wp_get_attachment_thumb_url($gimage->ID); ?>" class="attachment-thumbnail" alt="<?php echo $gimage->post_excerpt; ?>" title="<?php echo $gimage->post_title; ?>"/>
        </a>
        </span>
        </span>
        <?php
            $i++;
            if ($i % 3 == 0) {
                echo '<br style="clear: both;">';
                $i = 0;
            }
        }
        ?>
        <br style="clear: both;">
        <br style="clear: both;">
        </div>
        <?php
       // echo do_shortcode('[gallery id="'. $_GET['gallery'] .'" itemtag="span" icontag="span" captiontag="" link="file"]');
    }
    die();
}
?>
