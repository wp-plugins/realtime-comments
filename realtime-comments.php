<?php
/**
 * Plugin Name: Realtime Comments
 * New accepted comments from all users are updated in pages real-time, without the need to refresh the page. Allows comments section work interactively, like a chatroom. Pure WP plugin, no third party involvement, no account registration or third party application needed. Comments re-classified as trash, spam, or unapproved will be dynamically removed from users screen. 
 * Version: 0.6
 * Author: Eero Hermlin
 * Author URI: http://eero.hermlin.era.ee/
 * Requires at least: 3.0
 * Tested up to: 4.1.1
 * License: GPLv2
 */

if(!defined('REALTIMECOMMENTS_VERSION')) {
    define('REALTIMECOMMENTS_VERSION', '0.6');
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

    private $default_refresh = 1000;
    private $default_anim = 1;
    private $post_types = array();
    private $selected_pages = array();

    private $refresh_options=array(
        500  => '0.5 seconds',
        1000 => '1.0 second',
        1500 => '1.5 seconds',
        2000 => '2 seconds',
        3000 => '3 seconds',
        5000 => '5 seconds',
        10000=> '10 seconds',
        30000=> '30 seconds',
        60000=> '1 minute',
        );

    private $order_options=array(       // get_option('comment_order') {asc|desc}
        '' => 'as general setting',
        'asc' => 'to bottom',
        'desc' => 'to top',
    );

    public function __construct() {
        global $wp_version;

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


        $values=get_option('rtc-settings');

        if(is_array($values)) {
            // user has chosen own values
            if (isset($values['refresh'])) $this->refresh = $values['refresh']; 
            if (isset($values['anim'])) $this->anim = $values['anim'];
            if (isset($values['order'])) $this->order = $values['order'];
            if (isset($values['selected_pages']) && is_array($values['selected_pages'])) $this->selected_pages = $values['selected_pages'];
            if (isset($values['post_types']) && is_array($values['post_types'])) $this->post_types = $values['post_types'];
        } 
        $this->now=time();
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
        add_settings_field('refresh_field', 'Choose refresh frequency', array($this, 'refresh_input'), 'rtc_menu', 'rtc_main_settings'); 

        add_settings_field('order_field', 'New comments appear', array($this, 'order_input'), 'rtc_menu', 'rtc_main_settings');

        add_settings_field('limit_show', 'Use Realtime Comments for', array($this, 'select_pages'), 'rtc_menu', 'rtc_main_settings');

        // add_settings_field('anim_field', 'New comments flash', array($this, 'anim_input'), 'rtc_menu', 'rtc_main_settings'); 
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
            $output['refresh']=intval($input['refresh']);
        } else {
            $output['refresh']=$this->default_refresh;
        }
        if(isset($input['order'])) {
            $output['order']=$input['order'];
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
            $walker = new Rtc_Page_Selector_Walker($this->selected_pages);
            $options_list= wp_list_pages( array('title_li'=>'', 'post-type'=>'page','sort_column' => 'menu_order, post_title', 'echo'=>0, 'walker'=>$walker));
            $options_list=str_replace(array('</li>', "</ul>\n"), '', $options_list);
            $options_list=str_replace("<ul class='children'>\n", '    ', $options_list);
            echo $options_list;
        echo '</select>';

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
        update_comment_meta( $comment_id, 'rtc_last_modified', $this->now );
    }

    public function enqueue_style_n_script( $hook_suffix ) {
        global $post;

        if (
            (isset($post->ID) && in_array(get_post_type($post->ID), array_keys($this->post_types))) ||
            (isset($post->ID) && in_array($post->ID, $this->selected_pages))
            ) {
            wp_enqueue_script( 'rtc-plugin', plugins_url('js/script.js', __FILE__ ), array('jquery'), REALTIMECOMMENTS_VERSION, false );

            $data = array(
                'ajaxurl' => parse_url(admin_url('admin-ajax.php'), PHP_URL_PATH),
                'nonce' => wp_create_nonce('realtime-comments'),
                'refresh_interval' => $this->refresh,
                'bookmark' => $this->now,
                'postid' => $post->ID,
                'order' => ($this->order ? $this->order : get_option('comment_order'))
            );
            wp_localize_script('rtc-plugin', '$RTC', $data);
        }
    }

    private function get_comments_wp30($postid, $status, $bookmark) {
        /* needed for WP<3.5 where get_comments does not use WP_Comment_Query 
           post_id, status, bookmark are validated before
        
        */
        global $wpdb;

        if($status=='all') {
            $approved="(comment_approved = '1' OR comment_approved='0')";
        } else {
            $approved="comment_approved='$status'";
        }

        $comments = $wpdb->get_results( "SELECT c.*, cm.meta_value FROM $wpdb->comments c INNER JOIN $wpdb->commentmeta cm ON c.comment_ID=cm.comment_id AND cm.meta_key='rtc_last_modified' AND cm.meta_value>$bookmark WHERE c.comment_post_ID=$postid AND $approved ORDER BY cm.meta_value" );
        return $comments;

    }

    public function get_comments($postid, $status, $bookmark, &$new_coms) {
        global $wp_version;
        $args=array(
            'post_id'=>$postid,
            'order' => 'ASC',
            'status' => $status,    // all (= hold/0 approved/1), spam, trash
            'meta_key' => 'rtc_last_modified',
            'meta_type' => 'numeric',
            'meta_compare' => '>=',
            'meta_value' => $bookmark,
        );

        // this way works starting from 3.5
        if(version_compare($wp_version, '3.5', '>=')) {
            $comments = get_comments( $args );
        } else {
            // fallback method for old versions
            $comments = $this->get_comments_wp30( $postid, $status, $bookmark );
        }
        foreach($comments as $comment) {
            if($comment->meta_value) {
            $html='';
            if($comment->comment_approved=='1' || $comment->comment_approved=='approve') {
                if(version_compare($wp_version, '3.8', '<')) {
                    ob_start();
                    wp_list_comments(array('echo'=>false), array($comment));
                    $html = ob_get_clean();
                } else {
                    $html = wp_list_comments(array('echo'=>false), array($comment));
                }
            } 
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
        // $pageload = intval( $_POST['rtc_pageload'] );
        $postid = intval( $_POST['postid'] );
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
            'old_bookmark'=>$bookmark,
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