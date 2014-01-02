<?php
/**
 * @package Menu_Image
 * @version 1.0
 * @licence GPLv2
 */

/*
Plugin Name: Menu Image
Plugin URI: http://html-and-cms.com/plugins/menu-image/
Description: Provide uploading images to menu item
Author: Alex Davyskiba aka Zviryatko
Version: 1.3
Author URI: http://makeyoulivebetter.org.ua/
*/

/*  Copyright 2013  Zviryatko  (email : sanya.davyskiba@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// @feature: Media library supports.
// @feature: Ajax uploading images & ajax saving.

/**
 * Provide attaching images to menu items.
 *
 * @package Menu_Image
 */
class Menu_Image_Plugin {
    public function __construct() {
        add_action('init', array($this, 'menu_image_init'));
        add_filter('manage_nav-menus_columns', array($this, 'menu_image_nav_menu_manage_columns'), 11);
        add_action('save_post', array($this, 'menu_image_save_post_action'), 10, 2);
        add_filter('wp_edit_nav_menu_walker', array($this, 'menu_image_edit_nav_menu_walker_filter'));
        add_filter('walker_nav_menu_start_el', array($this, 'menu_image_nav_menu_item_filter'), 10, 4);
        add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));
        wp_register_script( 'menu-image-script',  plugins_url( basename( __DIR__ ) . '/menu-image.js' ), array (
          'jquery'
        ));
    }

    /**
     * Initialization action.
     *
     * Adding image sizes for most popular menu icon sizes. Adding thumbnail
     *  support to menu post type.
     * @todo: do anyone need so more sizes?
     * Maybe leave only one, as example: 36x36?
     */
    public function menu_image_init() {
        add_post_type_support('nav_menu_item', array('thumbnail'));
        add_image_size('menu-24x24', 24, 24);
        add_image_size('menu-36x36', 36, 36);
        add_image_size('menu-48x48', 48, 48);
    }

    /**
     * Adding images as screen options.
     *
     * If not checked screen option 'image', uploading form not showed.
     *
     * @param $columns
     * @return array
     */
    public function menu_image_nav_menu_manage_columns($columns) {

        return $columns + array('image' => __('Image', 'menu-image'));
    }

    /**
     * Saving post action.
     *
     * Saving uploaded images and attach/detach to image post type.
     *
     * @param $post_id
     * @param $post
     */
    public function menu_image_save_post_action($post_id, $post) {
        $menu_image_settings = array('menu_item_image_size', 'menu-item-image-title-position');
        foreach ($menu_image_settings as $setting_name) {
            if (isset($_POST[$setting_name][$post_id]) && !empty($_POST[$setting_name][$post_id])) {
                update_post_meta($post_id, "_$setting_name", esc_sql($_POST[$setting_name][$post_id]));
            }
        }

        if (isset($_POST["selected-menu-item-image-id-$post_id"]))
        {
            set_post_thumbnail($post,$_POST["selected-menu-item-image-id-$post_id"]);
        }

        if (isset($_POST["selected-menu-item-hover-id-$post_id"]))
        {
            $attachment_id = $_POST["selected-menu-item-hover-id-$post_id"];
            wp_update_post(array(
              'ID' => $attachment_id,
              'post_parent' => $post_id,
              'post_type' => "attachment"
            ));
        }

        if (isset($_POST['menu_item_remove_image'][$post_id]) && !empty($_POST['menu_item_remove_image'][$post_id])) {
            $args = array(
                'post_type' => 'attachment',
                'post_status' => null,
                'post_parent' => $post_id,
            );
            $attachments = get_posts($args);
            if ($attachments) {
                foreach ($attachments as $attachment) {
                    wp_delete_attachment($attachment->ID);
                }
            }
            foreach ($menu_image_settings as $meta) {
                delete_post_meta($post_id, "_$meta");
            }
        }
    }

    /**
     * Replacement edit menu walker class.
     *
     * @return string
     */
    public function menu_image_edit_nav_menu_walker_filter() {
        return 'Menu_Image_Walker_Nav_Menu_Edit';
    }

    /**
     * Replacement default menu item output.
     *
     * @fixme: change loading images data on loading menu items to up performance.
     *
     * @param string $item_output Default item output
     * @param object $item Menu item data object.
     * @param int $depth Depth of menu item. Used for padding.
     * @param object $args
     * @return string
     */
    public function menu_image_nav_menu_item_filter($item_output, $item, $depth, $args) {
        $attributes = !empty($item->attr_title) ? ' title="' . esc_attr($item->attr_title) . '"' : '';
        $attributes .= !empty($item->target) ? ' target="' . esc_attr($item->target) . '"' : '';
        $attributes .= !empty($item->xfn) ? ' rel="' . esc_attr($item->xfn) . '"' : '';
        $attributes .= !empty($item->url) ? ' href="' . esc_attr($item->url) . '"' : '';

        $image_size = get_post_meta($item->ID, '_menu_item_image_size', TRUE);
        $title_position = get_post_meta($item->ID, '_menu-item-image-title-position', TRUE);
        $classes = $attributes_classes = "menu-image-title-{$title_position}";
        $image_args = array(
            'post_parent' => $item->ID,
            'post_type' => 'attachment',
            'numberposts' => 1,
            'exclude' => get_post_thumbnail_id($item->ID),
        );
        $hovered_image = reset(get_posts($image_args));
        if ($hovered_image) {
            $hover_image_src = wp_get_attachment_image_src($hovered_image->ID, $image_size);
            $attributes_classes .= ' menu-image-hovered';
            $style = '';
            if (isset($hover_image_src[1]) && isset($hover_image_src[1])) {
                $width = $hover_image_src[1];
                $height = $hover_image_src[2] + 1; // +1px because span have small inline space..
                $style .= " style='width: {$width}px; height: {$height}px;'";
                $attributes .= " style='line-height: {$height}px'";
            }
            $image = "<span class='menu-image-hover-wrapper'" . $style . ">";
            $image .= get_the_post_thumbnail($item->ID, $image_size, "class=menu-image {$classes}");
            $image .= wp_get_attachment_image($hovered_image->ID, $image_size, FALSE, "class=hovered-image {$classes}");
            $image .= '</span>';
        } else {
            $image = get_the_post_thumbnail($item->ID, $image_size, "class=menu-image {$classes}");
        }

        $item_output = $args->before;
        $attributes .= " class='{$attributes_classes}'";
        $item_output .= '<a' . $attributes . '>';
        $link = $args->link_before . apply_filters('the_title', $item->title, $item->ID) . $args->link_after;
        switch ($title_position) {
            case 'hide':
                $item_output .= $image;
                break;
            case 'before':
                $item_output .= $link . $image;
                break;
            case 'after':
            default:
                $item_output .= $image . $link;
                break;
        }
        $item_output .= '</a>';
        $item_output .= $args->after;
        return $item_output;
    }

    /**
     * Loading additional scripts
     */
    public function add_admin_scripts() {
        wp_enqueue_media();
        wp_enqueue_script('menu-image-script');
    }
}

$menu_image = new Menu_Image_Plugin();

require_once(ABSPATH . 'wp-admin/includes/nav-menu.php');
class Menu_Image_Walker_Nav_Menu_Edit extends Walker_Nav_Menu_Edit {

    function start_el(&$output, $item, $depth, $args) {

        global $_wp_nav_menu_max_depth;
        $_wp_nav_menu_max_depth = $depth > $_wp_nav_menu_max_depth ? $depth : $_wp_nav_menu_max_depth;

        ob_start();
        $item_id = esc_attr($item->ID);
        $removed_args = array(
            'action',
            'customlink-tab',
            'edit-menu-item',
            'menu-item',
            'page-tab',
            '_wpnonce',
        );

        $original_title = '';
        if ('taxonomy' == $item->type) {
            $original_title = get_term_field('name', $item->object_id, $item->object, 'raw');
            if (is_wp_error($original_title)) {
                $original_title = FALSE;
            }
        } elseif ('post_type' == $item->type) {
            $original_object = get_post($item->object_id);
            $original_title = $original_object->post_title;
        }

        $classes = array(
            'menu-item menu-item-depth-' . $depth,
            'menu-item-' . esc_attr($item->object),
            'menu-item-edit-' . ((isset($_GET['edit-menu-item']) && $item_id == $_GET['edit-menu-item']) ? 'active' : 'inactive'),
        );

        $title = $item->title;

        if (!empty($item->_invalid)) {
            $classes[] = 'menu-item-invalid';
            /* translators: %s: title of menu item which is invalid */
            $title = sprintf(__('%s (Invalid)'), $item->title);
        } elseif (isset($item->post_status) && 'draft' == $item->post_status) {
            $classes[] = 'pending';
            /* translators: %s: title of menu item in draft status */
            $title = sprintf(__('%s (Pending)'), $item->title);
        }

        $title = empty($item->label) ? $title : $item->label;

        $item_image_size = get_post_meta($item_id, '_menu_item_image_size', TRUE);
        $image_size = empty($item_image_size) ? 'menu-36x36' : $item_image_size;
        $title_position = get_post_meta($item->ID, '_menu-item-image-title-position', TRUE);
        if (!$title_position) $title_position = 'after';
        // second image
        $args = array(
            'post_type' => 'attachment',
            'numberposts' => 1,
            'post_status' => null,
            'post_parent' => $item_id,
            'exclude' => get_post_thumbnail_id($item_id),
        );
        $hovered = reset(get_posts($args));
        ?>
    <li id="menu-item-<?php echo $item_id; ?>" class="<?php echo implode(' ', $classes); ?>">
        <dl class="menu-item-bar">
            <dt class="menu-item-handle">
                <span class="item-title"><?php echo esc_html($title); ?></span>
					<span class="item-controls">
						<span class="item-type"><?php echo esc_html($item->type_label); ?></span>
						<span class="item-order hide-if-js">
							<a href="<?php
                            echo wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action' => 'move-up-menu-item',
                                        'menu-item' => $item_id,
                                    ),
                                    remove_query_arg($removed_args, admin_url('nav-menus.php'))
                                ),
                                'move-menu_item'
                            );
                            ?>" class="item-move-up"><abbr title="<?php esc_attr_e('Move up'); ?>">&#8593;</abbr></a>
							|
							<a href="<?php
                            echo wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action' => 'move-down-menu-item',
                                        'menu-item' => $item_id,
                                    ),
                                    remove_query_arg($removed_args, admin_url('nav-menus.php'))
                                ),
                                'move-menu_item'
                            );
                            ?>" class="item-move-down"><abbr title="<?php esc_attr_e('Move down'); ?>">
                                    &#8595;</abbr></a>
						</span>
						<a class="item-edit" id="edit-<?php echo $item_id; ?>"
                           title="<?php esc_attr_e('Edit Menu Item'); ?>" href="<?php
                        echo (isset($_GET['edit-menu-item']) && $item_id == $_GET['edit-menu-item']) ? admin_url('nav-menus.php') : add_query_arg('edit-menu-item', $item_id, remove_query_arg($removed_args, admin_url('nav-menus.php#menu-item-settings-' . $item_id)));
                        ?>"><?php _e('Edit Menu Item'); ?></a>
					</span>
            </dt>
        </dl>

        <div class="menu-item-settings" id="menu-item-settings-<?php echo $item_id; ?>">
            <?php if ('custom' == $item->type) : ?>
                <p class="field-url description description-wide">
                    <label for="edit-menu-item-url-<?php echo $item_id; ?>">
                        <?php _e('URL'); ?><br/>
                        <input type="text" id="edit-menu-item-url-<?php echo $item_id; ?>"
                               class="widefat code edit-menu-item-url" name="menu-item-url[<?php echo $item_id; ?>]"
                               value="<?php echo esc_attr($item->url); ?>"/>
                    </label>
                </p>
            <?php endif; ?>
            <p class="description description-thin">
                <label for="edit-menu-item-title-<?php echo $item_id; ?>">
                    <?php _e('Navigation Label'); ?><br/>
                    <input type="text" id="edit-menu-item-title-<?php echo $item_id; ?>"
                           class="widefat edit-menu-item-title" name="menu-item-title[<?php echo $item_id; ?>]"
                           value="<?php echo esc_attr($item->title); ?>"/>
                </label>
            </p>

            <p class="description description-thin">
                <label for="edit-menu-item-attr-title-<?php echo $item_id; ?>">
                    <?php _e('Title Attribute'); ?><br/>
                    <input type="text" id="edit-menu-item-attr-title-<?php echo $item_id; ?>"
                           class="widefat edit-menu-item-attr-title"
                           name="menu-item-attr-title[<?php echo $item_id; ?>]"
                           value="<?php echo esc_attr($item->post_excerpt); ?>"/>
                </label>
            </p>

            <p class="field-link-target description">
                <label for="edit-menu-item-target-<?php echo $item_id; ?>">
                    <input type="checkbox" id="edit-menu-item-target-<?php echo $item_id; ?>" value="_blank"
                           name="menu-item-target[<?php echo $item_id; ?>]"<?php checked($item->target, '_blank'); ?> />
                    <?php _e('Open link in a new window/tab'); ?>
                </label>
            </p>

            <p class="field-css-classes description description-thin">
                <label for="edit-menu-item-classes-<?php echo $item_id; ?>">
                    <?php _e('CSS Classes (optional)'); ?><br/>
                    <input type="text" id="edit-menu-item-classes-<?php echo $item_id; ?>"
                           class="widefat code edit-menu-item-classes" name="menu-item-classes[<?php echo $item_id; ?>]"
                           value="<?php echo esc_attr(implode(' ', $item->classes)); ?>"/>
                </label>
            </p>

            <?php $placeholder = "<img id=\"menu-%s-preview-{$item_id}\" class=\"media-upload\" width=\"48\" height=\"48\" src=\"/wp-includes/images/crystal/default.png\">"; ?>
            <p class="field-image description description-thin">
              <label for="edit-menu-item-image-<?php echo $item_id; ?>" data-id="<?php echo $item_id; ?>" data-type="image">
                <?php _e('Image', 'menu-image'); ?><br/>
                <?php echo has_post_thumbnail($item_id)?get_the_post_thumbnail($item_id, $image_size, array('class'=>'media-upload')):sprintf($placeholder,"image"); ?><br/>
                <input type="hidden" id="selected-menu-item-image-id-<?php echo $item_id?>" name="selected-menu-item-image-id-<?php echo $item_id?>" />
              </label>
            </p>

            <p class="field-hover description description-thin">
              <label for="edit-menu-item-hover-<?oho echo $item_id; ?>" data-id="<?php echo $item_id; ?>" data-type="hover">
                <?php _e('Hover Image', 'menu-image'); ?><br/>
                <?php echo (!empty($hovered))?wp_get_attachment_image($hovered->ID, $image_size, false, array('class'=>'media-upload')):sprintf($placeholder,"hover"); ?><br/>    
                <input type="hidden" id="selected-menu-item-hover-id-<?php echo $item_id?>" name="selected-menu-item-hover-id-<?php echo $item_id?>" />
              </label>
            </p>

            <p class="field-image-data description description-wide">
              <label for="menu_item_image_size[<?php echo $item_id; ?>]">
                <?php _e("Size", 'menu-image'); ?><br />
                <select name="menu_item_image_size[<?php echo $item_id; ?>]">
                <?php foreach (get_intermediate_image_sizes() as $size) : ?>
                  <option value="<?php echo $size; ?>"<?php echo ($image_size == $size)?' selected':''; ?>><?php echo ucfirst($size); ?></option>
                <?php endforeach; ?>
                </select>
              </label><br />
              <label for="menu-item-image-title-position[<?php echo $item_id; ?>]">
                <?php _e("Title position", "menu-image"); ?><br />
                <?php $positions = array('before', 'hide', 'after'); ?>
                <?php foreach ($positions as $key => $position) : ?>
                    <input type="radio" name="menu-item-image-title-position[<?php echo $item_id; ?>]" value="<?php echo $position; ?>"<?php echo ($title_position == $position)?' checked':''; ?>/> <?php _e(ucfirst($position)); ?>
                    <?php if (isset($positions[$key + 1])) echo " | " ?>
                <?php endforeach; ?>
              </label>
            </p>          

            <p class="field-xfn description description-thin">
                <label for="edit-menu-item-xfn-<?php echo $item_id; ?>">
                    <?php _e('Link Relationship (XFN)'); ?><br/>
                    <input type="text" id="edit-menu-item-xfn-<?php echo $item_id; ?>"
                           class="widefat code edit-menu-item-xfn" name="menu-item-xfn[<?php echo $item_id; ?>]"
                           value="<?php echo esc_attr($item->xfn); ?>"/>
                </label>
            </p>

            <p class="field-description description description-wide">
                <label for="edit-menu-item-description-<?php echo $item_id; ?>">
                    <?php _e('Description'); ?><br/>
                    <textarea id="edit-menu-item-description-<?php echo $item_id; ?>"
                              class="widefat edit-menu-item-description" rows="3" cols="20"
                              name="menu-item-description[<?php echo $item_id; ?>]"><?php echo esc_html($item->description); // textarea_escaped ?></textarea>
                    <span
                        class="description"><?php _e('The description will be displayed in the menu if the current theme supports it.'); ?></span>
                </label>
            </p>

            <div class="menu-item-actions description-wide submitbox">
                <?php if ('custom' != $item->type && $original_title !== FALSE) : ?>
                    <p class="link-to-original">
                        <?php printf(__('Original: %s'), '<a href="' . esc_attr($item->url) . '">' . esc_html($original_title) . '</a>'); ?>
                    </p>
                <?php endif; ?>
                <a class="item-delete submitdelete deletion" id="delete-<?php echo $item_id; ?>" href="<?php
                echo wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'delete-menu-item',
                            'menu-item' => $item_id,
                        ),
                        remove_query_arg($removed_args, admin_url('nav-menus.php'))
                    ),
                    'delete-menu_item_' . $item_id
                ); ?>"><?php _e('Remove'); ?></a> <span class="meta-sep"> | </span> <a class="item-cancel submitcancel"
                                                                                       id="cancel-<?php echo $item_id; ?>"
                                                                                       href="<?php    echo esc_url(add_query_arg(array('edit-menu-item' => $item_id, 'cancel' => time()), remove_query_arg($removed_args, admin_url('nav-menus.php'))));
                                                                                       ?>#menu-item-settings-<?php echo $item_id; ?>"><?php _e('Cancel'); ?></a>
            </div>

            <input class="menu-item-data-db-id" type="hidden" name="menu-item-db-id[<?php echo $item_id; ?>]"
                   value="<?php echo $item_id; ?>"/>
            <input class="menu-item-data-object-id" type="hidden" name="menu-item-object-id[<?php echo $item_id; ?>]"
                   value="<?php echo esc_attr($item->object_id); ?>"/>
            <input class="menu-item-data-object" type="hidden" name="menu-item-object[<?php echo $item_id; ?>]"
                   value="<?php echo esc_attr($item->object); ?>"/>
            <input class="menu-item-data-parent-id" type="hidden" name="menu-item-parent-id[<?php echo $item_id; ?>]"
                   value="<?php echo esc_attr($item->menu_item_parent); ?>"/>
            <input class="menu-item-data-position" type="hidden" name="menu-item-position[<?php echo $item_id; ?>]"
                   value="<?php echo esc_attr($item->menu_order); ?>"/>
            <input class="menu-item-data-type" type="hidden" name="menu-item-type[<?php echo $item_id; ?>]"
                   value="<?php echo esc_attr($item->type); ?>"/>
        </div>
        <!-- .menu-item-settings-->
        <ul class="menu-item-transport"></ul>
        <?php
        $output .= ob_get_clean();
    }
}
