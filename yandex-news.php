<?php
/*
Plugin Name: Yandex News
Author: Yevhen Amelin <yevhen.amelin@gmail.com>
Description: Generates an XML feed to export news in the Yandex.News service
Text Domain: yandex-news
Version: 0.2
*/ 

class YandexNews
{
    private $options;
    private $feedname;

    public function __construct()
    {
        $this->options = get_option( 'yandex_news' );
        $this->feedname = ( !empty( $o['path'] ) ) ? $o['path'] : 'yandex';

        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'pre_get_posts', array( $this, 'query_alter' ) );
        add_action( 'init', array( $this, 'add_feed' ) );

        load_plugin_textdomain('yandex-news', false, basename( dirname(
        __FILE__ ) ) . '/languages' );
    }

    public function add_feed()
    {
        if ( empty( $this->options['image'] ) )
            return;

        add_action( 'do_feed_'.$this->feedname, array( $this, 'do_feed' ) );

        add_rewrite_rule(
            'feed/' . $this->feedname . '/?$',
            'index.php?feed=' . $this->feedname,
            'top'
        );
        flush_rewrite_rules();
    }

    public function add_settings_page()
    {
        add_options_page(
            __( 'Yandex.News plugin settings page', 'yandex-news' ),
            __( 'Yandex.News', 'yandex-news' ), 
            'manage_options', 
            'yandex-news-admin', 
            array( $this, 'create_settings_page' )
        );
    }

    public function create_settings_page()
    {
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php _e( 'Yandex News Settings', 'yandex-news' ) ?></h2>
            <form method="post" action="options.php">
            <?php
                settings_fields( 'yandex_news_option_group' );   
                do_settings_sections( 'yandex-news-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    public function page_init()
    {        
        register_setting(
            'yandex_news_option_group',
            'yandex_news',
            array( $this, 'sanitize' )
        );

        add_settings_section(
            'yandex_news_feed_settings',
            __( 'Main Feed Settings', 'yandex-news' ),
            array( $this, 'print_section_info' ),
            'yandex-news-admin'
        );  
        
        add_settings_field(
            'path',
            __( 'Path', 'yandex-news' ),
            array( $this, 'path_callback' ),
            'yandex-news-admin',
            'yandex_news_feed_settings'
        );      

        add_settings_field(
            'image',
            __( 'Image', 'yandex-news' ),
            array( $this, 'image_callback' ),
            'yandex-news-admin',
            'yandex_news_feed_settings'
        );
        
        add_settings_field(
            'categories',
            __( 'Categories', 'yandex-news' ),
            array( $this, 'categories_callback' ),
            'yandex-news-admin',
            'yandex_news_feed_settings'
        );

    }

    public function sanitize( $input )
    {
        $new_input = array();
        if ( isset( $input['path'] ) )
            $new_input['path'] = sanitize_text_field( $input['path'] );

        $new_input['image'] = $this->sanitize_image( $input['image'] );
        
        if ( isset( $input['categories'] ) )
            foreach( $input['categories'] as $category )
                if ( absint( $category ) )
                    $new_input['categories'][] = $category;

        return $new_input;
    }
    
    public function sanitize_image( $image=null ) {
        if ( !$image )
            $image = $this->options['image'];

            
        $error = false;
        if ( isset( $image ) ) {
          $new_image = esc_url_raw( $image );
          $headers = get_headers( $new_image, true );
          
          if ( mb_strpos( $headers['Content-Type'], 'image' ) === false )
              $error = true;
        }
        
        if ( strlen( $new_image ) == 0 || $error )
            add_settings_error(
              'image',
              esc_attr( 'settings_updated' ),
              __('You must specify a valid image URL', 'yandex-news')
            );
        
        return $new_image;
    }

    public function print_section_info()
    {
        $this->sanitize_image();
        if ( $this->options['image'] && !get_settings_errors( 'image' ) ) {
            echo '<p style="color: green; font-weight: bold">' . __( 'Your feed is active', 'yandex-news' ) . '</p>';
            printf( '<p>' . __( 'Feed URL: <a href="%s" target="_blank">%1$s</a>',
            'yandex-news' ) . '</p>', get_bloginfo('url') . '/feed/' .
            $this->feedname);
        }
        else {
            settings_errors( 'image', true, true );
            echo '<p class="error-message">' .
                __( 'Your feed is not active', 'yandex-news' ) . '</p>';
        }
    }
    
    public function path_callback()
    {
        printf(
            '<input class="regular-text" type="text" id="path"
            name="yandex_news[path]" value="%s" /><p class="description">' .
            __('Feed name', 'yandex-news') . '</p>',
            $this->feedname
        );
    }

    public function image_callback()
    {
        printf(
            '<input class="regular-text" type="text" id="image"
            name="yandex_news[image]" value="%s" /><p class="description">' .
            __('Feed image', 'yandex-news') . ' (' .
            __('required field', 'yandex-news') . ')</p>', 
            isset( $this->options['image'] ) ? esc_attr(
            $this->options['image'] ) :
            ''
        );
    }
    
    public function categories_callback()
    {
        $categories = get_categories( array('hide_empty' => 0) );

        foreach( $categories as $category ) {
            $state = 0;
            if ( isset( $this->options['categories'] ) and
                in_array( $category->cat_ID, $this->options['categories'] ) )
                $state = 1;

            printf(
                '<nobr style="background: #f7f7f7; padding: .5em 1em;
                line-height: 2.5em"><input type="checkbox" id="category_%d"
                name="yandex_news[categories][]" value="%1$d" %s /> %s</nobr> ',
                $category->cat_ID, checked( $state, 1, false ), $category->name
            );
        }
    }

    public function query_alter( $query )
    {
        if ( isset ( $query->query_vars['feed'] ) and 
             $query->query_vars['feed'] == $this->feedname ) {

            if ( $this->options['categories'] ) {
                $cats = join( ',', $this->options['categories'] );
                $query->set( 'cat', $cats );
            }
        }
    }

    public function do_feed()
    {
        load_template( plugin_dir_path( __FILE__ ) . 'feed.php' );
    }

}

$yanews = new YandexNews();

?>
