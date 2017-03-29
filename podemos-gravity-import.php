<?php
/*
Plugin Name: Podemos Gravity import
Description: Importa información de los formularios de gravity.
Text Domain: pgi-import
Author: Podemos
Version: 1.0
*/

function gravity_import_activate() {
	$composer = shell_exec("whereis composer");
	$path = explode(" ", $composer);
	if (empty($path[1])) {
		throw new Exception("Error inicializando plugin", 1);
		
	}
	echo shell_exec('cd ../wp-content/plugins/podemos-gravity-import && export COMPOSER_HOME='.$path[1].' 2>&1 && composer install');
}
register_activation_hook( __FILE__, 'gravity_import_activate' );

/*Ahora que las categorias se registran con un botón puede ir esto dentro del admin?*/
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'src/pgi_formularios.php';
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'src/pgi_votaciones.php';

if ( is_admin() ) {
	if (is_dir("../wp-content/plugins/podemos-gravity-import/vendor")) {
		require __DIR__ . '/vendor/autoload.php';
	    require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'adminPage.php';
	    add_action( 'init', array( 'adminPage', 'init' ) );
	} else {
		?>
      <div class="error notice">
        <p><?php _e( 'No se instalo bien composer', 'pgi-import' ); ?></p>
      </div>
    <?php
	}
}

function pgi_theme_style() {
    wp_enqueue_style('pgi_theme_style', plugin_dir_url( __FILE__ ) . '/style.css');
}
add_action('admin_enqueue_scripts', 'pgi_theme_style');
