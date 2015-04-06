<?php
/**
 * Plugin Name: Realtime Comments
 * New accepted comments from all users are updated in pages real-time, without the need to refresh the page. Allows comments section work interactively, like a chatroom. Pure WP plugin, no third party involvement, no account registration or third party application needed. Comments re-classified as trash, spam, or unapproved will be dynamically removed from users screen. 
 * Version: 0.7
 * Author: Eero Hermlin
 * Author URI: http://eero.hermlin.era.ee/
 * Requires at least: 3.0
 * Tested up to: 4.1.1
 * License: GPLv2
 */

if(!defined('REALTIMECOMMENTS_VERSION')) {
    define('REALTIMECOMMENTS_VERSION', '0.7');
}

if(!defined('ABSPATH')) {
  die('You are not allowed to call this page directly.');
}

require_once(plugin_dir_path(__FILE__).'rtc-page-selector-walker.class.php');


class RealTimeComments {
    private $refresh = 2000;
    private $anim = 1;
    private $order = false; // {'asc'|'desc'|false}
    private $now = 0;
    private $old_wp = false;
    private $mode = 'all'; // {'all'|'new'}

    private $default_anim = 1;
    private $post_types = array();
    private $selected_pages = array();
    private $max_c_id = 0;

    private $default_refresh = 2000;

    private $refresh_options=array(
        500  => '0.5',
        1000 => '1.0',
        1500 => '1.5',
        2000 => '2',
        3000 => '3',
        5000 => '5',
        10000=> '10',
        30000=> '30',
        60000=> '60',
        );

    private $order_options = array(       // get_option('comment_order') {asc|desc}
        '' => 'as general setting',
        'asc' => 'to bottom',
        'desc' => 'to top',
    );

    private $default_avatar_size = '56';

    private $avatar_size_options = array (
        '34' => '34px (Twenty Fourteen)',
        '40' => '40px (Twenty Ten)',
        '44' => '44px (Twenty Twelve)',
        '50' => '50px',
        '56' => '56px',
        '62' => '62px',
        '68' => '68px (Twenty Eleven)',
        '74' => '74px (Twenty Thirteen)',
    );

    public function __construct() {
        global $wp_version, $post;
        $this->now=time();

        if(is_admin()) {
            // implement admin screen updates
            register_activation_hook( __FILE__, array( $this, 'install' ) );
            register_deactivation_hook( __FILE__, array( $this, 'uninstall' ) );
            if(version_compare($wp_version, '3.0', '<')) {
                add_action( 'admin_notices', array($this, 'wp_version_error'));
            }
            if(is_admin()) {
                add_action( 'admin_menu', array($this, 'admin_page')); 
                add_action( 'admin_init', array($this, 'register_and_build_fields')); 
                add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array($this, 'plugin_settings_link') );
            }
        } else {
            // add_action( 'wp_head', array($this,'rtc_ajaxurl'));
            add_action( 'wp_enqueue_scripts', array($this, 'enqueue_style_n_script') );        
        }
        add_action( 'wp_ajax_rtc_update', array($this, 'rtc_update') );
        add_action( 'wp_ajax_nopriv_rtc_update', array($this, 'rtc_update') );
        add_action( 'wp_set_comment_status', array($this, 'update_last_modified') );
        add_action( 'wp_insert_comment', array($this, 'update_last_modified') );
        add_action( 'edit_comment', array($this, 'update_last_modified') );
        add_action( 'realtime_comments_cleanup', array($this, 'cleanup') );
        add_filter( 'wp_list_comments_args', array($this, 'reverse_comments'));


        $values=get_option('rtc-settings');

        if(is_array($values)) {
            if (isset($values['refresh'])) $this->refresh = $values['refresh']; else $this->refresh = $this->default_refresh;
            if (isset($values['anim'])) $this->anim = $values['anim'];
            if (isset($values['order'])) $this->order = $values['order'];
            if (isset($values['avatar_size'])) $this->avatar_size = $values['avatar_size']; else $this->avatar_size = $this->default_avatar_size;
            if (isset($values['selected_pages']) && is_array($values['selected_pages'])) $this->selected_pages = $values['selected_pages'];
            if (isset($values['post_types']) && is_array($values['post_types'])) $this->post_types = $values['post_types'];
        } 


        // Comments per page: get_option('comments_per_page') (counting top-level comments)
        // Comments order: get_option('comment_order') {asc|desc}
        // pagination links https://codex.wordpress.org/Template_Tags/paginate_comments_links
        // 
    }

    /* 
    ========================================================================== 

                           S E T U P     F U N C T I O N S

    ========================================================================== 
    */


    public function reverse_comments($args) {
        global $post;
        if ($this->order && ($this->order != get_option('comment_order'))) { 
            if (
             (isset($post->ID) && in_array(get_post_type($post->ID), array_keys($this->post_types))) ||
             (isset($post->ID) && in_array($post->ID, $this->selected_pages))
            ) {
                $args['reverse_top_level'] = true;
            }
        }
        return $args;
    }


    /* 
    ========================================================================== 

                           A D M I N     F U N C T I O N S

    ========================================================================== 
    */

    // Add settings link on plugin page
    function plugin_settings_link($links) { 
      $settings_link = '<a href="admin.php?page=rtc_admin_menu">Settings</a>'; 
      array_push($links, $settings_link); 
      return $links; 
    }    
    
    public function admin_page() { 
        // add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
        add_menu_page('Realtime Comments', 'Realtime Comments', 'administrator', 'rtc_admin_menu', array($this, 'create_menu_page'), '', '25.000154');

    }

    public function create_menu_page() {
        ?> 
        <div id="my-options-wrap"> 
        <div class="icon32" id="icon-tools"> <br /> </div> 
            <form method="post" action="options.php" enctype="multipart/form-data"> 
            <?php settings_fields('rtc_settings'); ?> 
            <?php do_settings_sections('rtc_menu'); ?> 
            <p class="submit"> <input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save'); ?>" /> </p> 
            </form> 
        </div> 
        <?php     
    }

    function register_and_build_fields() { 
        // register_setting( $option_group, $option_name, $sanitize_callback );
        register_setting('rtc_settings', 'rtc-settings', array($this, 'validate_my_option')); 

        // add_settings_section( $id, $title, $callback, $page );
        add_settings_section('rtc_main_settings', 'Realtime Comments', array($this, 'create_rtc_intro'), 'rtc_menu'); 

        // add_settings_field( $id, $title, $callback, $page, $section, $args );
        add_settings_field('refresh_input', 'Refresh frequency (seconds)', array($this, 'refresh_input'), 'rtc_menu', 'rtc_main_settings'); 

        add_settings_field('order_input', 'New comments appear', array($this, 'order_input'), 'rtc_menu', 'rtc_main_settings');

        add_settings_field('select_pages', 'Use Realtime Comments for', array($this, 'select_pages'), 'rtc_menu', 'rtc_main_settings');

        add_settings_field('avatar_size', 'Avatar size (not important for responsive themes)', array($this, 'avatar_size_input'), 'rtc_menu', 'rtc_main_settings'); 
    } 

    function create_rtc_intro() {
        echo '<p>Settings for plugin</p>';
    }

    function validate_my_option($input) { 
        // validate entered value
        $output=array();
        var_dump($input);
        if(isset($input['anim'])) $output['anim']=1; else $output['anim']=0;
        if(isset($input['refresh']) && array_key_exists(intval($input['refresh']), $this->refresh_options)) {
            $output['refresh'] = intval($input['refresh']);
        } else {
            $output['refresh'] = $this->default_refresh;
        }
        if(isset($input['order'])) {
            $output['order'] = $input['order'];
        } 
        if(isset($input['avatar_size'])) {
            $output['avatar_size'] = $input['avatar_size'];
        }
        if(is_array($input['post_types'])) {
            $output['post_types']=$input['post_types'];
        } else {
            $output['post_types']=array();
        }
        if(is_array($input['selected_pages'])) {
            $output['selected_pages']=$input['selected_pages'];
        } else {
            $output['selected_pages']=array();
        }
        return $output; 
    } 

    function refresh_input() { 
        echo '<select name="rtc-settings[refresh]">'."\n";
        foreach($this->refresh_options as $value=>$text) {
            echo '     <option value="'.$value.'" '.selected($this->refresh, $value, false).'>'.$text.'</option>'."\n";
        }
        echo '</select>'."\n";
    } 


    function order_input() {
        echo '<select name="rtc-settings[order]">'."\n";
        foreach($this->order_options as $value=>$text) {
            echo '    <option value="'.$value.'" '.selected($this->order, $value, false).'>'.$text.'</option>'."\n";
        }
        echo '</select>'."\n";
    }

    function select_pages() {
        echo '<input type="checkbox" name="rtc-settings[post_types][page]" value="1" '.checked(true, isset($this->post_types['page']), false).'>all Pages<br>';
        echo '<input type="checkbox" name="rtc-settings[post_types][post]" value="1" '.checked(true, isset($this->post_types['post']), false).'>all Posts<br>';
        echo 'and/or on following pages:<br>';
        echo '<select name="rtc-settings[selected_pages][]" multiple="multiple" size="8" style="height: 14em">';
        echo Rtc_Page_Selector_Walker::get_pages_selection($this->selected_pages);
        echo '</select>';

    }

    function avatar_size_input() {
        echo '<select name="rtc-settings[avatar_size]">'."\n";
        foreach($this->avatar_size_options as $value=>$text) {
            echo '    <option value="'.$value.'" '.selected($this->avatar_size, $value, false).'>'.$text.'</option>'."\n";
        }
        echo '</select>'."\n";    
    }

    function anim_input() { 
        ?><input name="rtc-settings[anim]" type="checkbox" value="1" <?=($this->anim ? 'checked="1"':'') ?>><?php
    } 


    /* 
    ========================================================================== 

                      F R O N T E N D     F U N C T I O N S

    ========================================================================== 
    */
    public function update_last_modified($comment_id) {
        // using intentionally "time()" + tambov instead of "$this->now" variable, to save as late time as possible
        // this is needed to not lose any comments due to parallelism
        // not using comment own timestamp, because this is not accurate in case of comment editing
        // 
        update_comment_meta( $comment_id, 'rtc_last_modified', time() );
    }

    public function enqueue_style_n_script( $hook_suffix ) {
        global $post;

        if (
            (isset($post->ID) && in_array(get_post_type($post->ID), array_keys($this->post_types))) ||
            (isset($post->ID) && in_array($post->ID, $this->selected_pages))
            ) {
            wp_enqueue_script( 'rtc-plugin', plugins_url('js/script.js', __FILE__ ), array('jquery'), REALTIMECOMMENTS_VERSION, false );

            $page_comments = get_option('page_comments');
            /*
            $page = get_query_var('cpage');
            $nextpage = intval($page) + 1;
            $max_page = get_comment_pages_count();
            if ( empty($max_page) )
                $max_page = $wp_query->max_num_comment_pages;
            if ( empty($max_page) )
                global $wp_query;
                $max_page = $wp_query->max_num_comment_pages;
            */

            $data = array(
                'ajaxurl' => parse_url(admin_url('admin-ajax.php'), PHP_URL_PATH),
                'nonce' => wp_create_nonce('realtime-comments'),
                'refresh_interval' => $this->refresh,
                'bookmark' => $this->now,
                'max_c_id' => $this->get_max_comment_id($post->ID),
                'postid' => $post->ID,
                'order' => ($this->order ? $this->order : get_option('comment_order')),
                'comments_per_page' => ($page_comments ? get_option('comments_per_page') : 0),
                'comment_list_el' => '#comments',
                'comment_list_tag' => 'ol',
                'comment_list_class' => null, // 'comment-list',
                'comment_tag' => 'li',
                'comment_id_prefix' => '#comment-',
                'children_class' => 'children',
                'tambov' => 5,
                'is_last_page' => (($page_comments && is_numeric(get_query_var('cpage'))) ? '0' : '1'),
                // 'max_page' => $max_page,
            );
            wp_localize_script('rtc-plugin', '$RTC', $data);
        }
    }

    private function get_max_comment_id($postid) {
        global $wpdb;
            // $comments = $wpdb->get_results( "SELECT max(c.comment_ID) as r FROM $wpdb->comments c WHERE c.comment_post_ID=$postid" );  
            $comments = $wpdb->get_results( "SELECT max(c.comment_ID) as r FROM $wpdb->comments c" );  
            return max(0, $comments[0]->r);
    }

    private function get_comments_wp30($postid, $status, $bookmark, $max_id) {
        /* needed for WP<3.5 where get_comments does not use WP_Comment_Query 
           post_id, status, bookmark are validated before
        
        */
        global $wpdb;

        if($status=='all') {
            $approved="(comment_approved = '1' OR comment_approved='0')";
        } else {
            $approved="comment_approved='$status'";
        }
        if ($this->mode == 'all') {
            $comments = $wpdb->get_results( "SELECT c.*, cm.meta_value FROM $wpdb->comments c INNER JOIN $wpdb->commentmeta cm ON c.comment_ID=cm.comment_id AND cm.meta_key='rtc_last_modified' AND (cm.meta_value>=$bookmark OR cm.comment_ID>$max_id) WHERE c.comment_post_ID=$postid AND $approved ORDER BY c.comment_ID" );
        } else {
            $comments = $wpdb->get_results( "SELECT c.*, '1' AS meta_value FROM $wpdb->comments c WHERE c.comment_post_ID=$postid AND c.comment_ID>$max_id AND $approved ORDER BY c.comment_ID" );        
        }
        return $comments;

    }

    public function get_comments($postid, $status, $bookmark, &$new_coms) {
        global $wp_version;

        $comments = $this->get_comments_wp30( $postid, $status, $bookmark, $this->max_c_id );

        foreach($comments as $comment) {
            if($comment->meta_value) {
            $html = '';
            $args=array('echo' => false, 'style' => 'ol', 'avatar_size' => $this->avatar_size);
            if($comment->comment_approved=='1' || $comment->comment_approved=='approve') {
                if(version_compare($wp_version, '3.8', '<')) {
                    ob_start();
                    wp_list_comments($args, array($comment));
                    $html = ob_get_clean();
                } else {
                    $html = wp_list_comments($args, array($comment));
                }
            } 
            $this->max_c_id = max($this->max_c_id, (int) $comment->comment_ID);
            $new_coms[]=array(
                'id'=>$comment->comment_ID,
                'parent'=>$comment->comment_parent,
                'approved'=>$comment->comment_approved,
                'rtc_last_modified'=>$comment->meta_value,
                'html'=>$html,
                );
            }
        }
    }

    function rtc_update() {
        global $wpdb;
        $bookmark = intval( $_POST['rtc_bookmark'] );
        $postid = intval( $_POST['postid'] );
        $max_c_id = intval($_POST['max_c_id']);
        $this->max_c_id = max($this->max_c_id, $max_c_id);
        $comments=false;
 
        // die('{"status":500,"error":"Enough"}');

        if(!isset($postid)) {
            die('{"status":500,"msg":"Postid not set"');
        }

        $new_coms=array();
        foreach(array('all', 'spam', 'trash') as $status) {
            $this->get_comments($postid, $status, $bookmark, $new_coms);
        }

        if(!count($new_coms)) {
            die('{"status":304, "bookmark":'.$this->now.'}');    
            // die('Ei');
        }

        $response=array(
            'status'=>200,
            'bookmark'=>$this->now,
            'max_c_id'=>''.$this->max_c_id,
            'comments'=>$new_coms,
            );
        die(json_encode($response));
    }

    public function cleanup() {
        /* To limit growth of commentmeta table, not needed 'rtc_last_modified' meta's will be deleted
            Definition of "not needed": older than 2x $this->refresh 
        */
        global $wpdb;
        $wpdb->query( 
            $wpdb->prepare( 
                "DELETE FROM $wpdb->commentmeta WHERE meta_key = %s AND meta_value < %d",
                'rtc_last_modified', 
                $this->now-($this->refresh/500) 
                )
            );
    }
    /* 
    ========================================================================== 

                       S T A T I C     F U N C T I O N S

    ========================================================================== 
    */
    public static function install() {
        if ( ! wp_next_scheduled( 'realtime_comments_cleanup' ) ) {
          wp_schedule_event( time(), 'hourly', 'realtime_comments_cleanup' );
        }    
        $values=get_option('rtc-settings');
        if(!isset($values['post_types'])) {
            $values['post_types'] = array('post' => '1', 'page' => '1');
        }
        if(!isset($values['selected_pages'])) {
            $values['selected_pages'] = array();
        }
        update_option('rtc-settings', $values);
    }

    public static function uninstall() {
        global $wpdb;
        // clean up. delete options and commentmeta
        // delete_option( 'rtc-settings' );
        $wpdb->query("DELETE FROM $wpdb->commentmeta WHERE meta_key = 'rtc_last_modified'");
    }

    public static function wp_version_error() {
        global $wp_version;
        ?>
        <div class="error below-h2">
        <p>Realtime Comments plugin is tested to be working on Wordpress 3.0 or newer versions. You have <?=$wp_version ?></p>
        </div>
        <?php    
    }
}

new RealTimeComments();