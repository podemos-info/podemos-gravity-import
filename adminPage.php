<?php

/**
* 
*/
define(PGI_PLUGIN_PATH, dirname( __FILE__ ));
class adminPage
{
    private static $initiated = false;

    public static function init() {
        if ( ! self::$initiated ) {
            self::init_hooks();
        }
    }

    public static function init_hooks(){
        self::$initiated = true;
        add_action('admin_menu', array( 'adminPage', 'pgi_config_page'));
        add_action('admin_menu', array( 'adminPage', 'pgi_registro_importación'));
        add_action('admin_init', array( 'adminPage', 'pgi_content_settings'));
        /* Ajax actopms */
        add_action( 'admin_footer', array('adminPage', 'import_action_javascript') ); // Write our JS below here
        add_action( 'wp_ajax_import_action', array('pgi_gravity_import', 'import_action_callback' ));
        add_action('admin_menu', array( 'adminPage', 'pgi_procesar_votaciones' ));
        


    }

    public static function pgi_config_page (){
        add_menu_page(
                'Registra custom post tail para tu formulario',                 //Título de la página
                'Gravity import',                                               //Título del menú
                'manage_options',                                               //rol que puede acceder
                basename ( PGI_PLUGIN_PATH ),
                array( 'adminPage','pgi_content_page_registro_importacion'),     //Función que pinta la página de configuración
                'dashicons-admin-settings'                                       //Icono de menú
            );
    }

    public static function pgi_registro_importación (){
        add_submenu_page(
                basename ( PGI_PLUGIN_PATH ),
                'Ajustes de configuracion de Podemos gravity import',         
                'Gravity import config',                                 
                'manage_options',                                      
                basename ( PGI_PLUGIN_PATH )."/registro-importacion",   
                array( 'adminPage','pgi_content_page_settings')     
            );
    }

    public static function pgi_procesar_votaciones (){
        add_submenu_page(
                basename ( PGI_PLUGIN_PATH ),
                'Procesar resultados de la votación',         
                'Procesar resultados',                                 
                'manage_options',                                      
                basename ( PGI_PLUGIN_PATH )."/procesar-resultados",   
                array( 'adminPage','pgi_page_result_process')     
            );
    }

    public static function pgi_content_page_registro_importacion(){
        ?>
            <div class="wrap">
                <h2>Registra un nuevo custom post type para un nuevo formulario</h2>   
        <?php
        
        if (
            get_option('pgi_base_url') &&
            get_option('pgi_api_key') &&
            get_option('pgi_private_key')
            ) {
                pgi_gravity_import::run_import_task();       
        } else {
            ?>
                <div class="error notice">
                    <p><?php _e( 'Debes configurar los datos de acceso a gravity', 'pgi-import' ); ?></p>
                </div>
            <?php
        }
        ?> 
            </div>
        <?php     
    }

    public static function pgi_content_page_settings(){
        ?>
            <div class="wrap">
                <h2>Configuración para importación de formularios gravity a post type</h2>
        <?php 
        if(!isset($_POST['submit'])){
            adminPage::pintar_formulario_settings();
        }
        ?> 
            </div>
        <?php     
    }
    
    public static function pgi_page_result_process(){

        if (!empty($_FILES)) {
            $target_dir = plugin_dir_path(__FILE__).'src/resultados/';
            $target_file = $target_dir . basename($_FILES["resultFile"]["name"]);
            $uploadOk = 1;
            $FileType = pathinfo($target_file,PATHINFO_EXTENSION);
            if($FileType != "csv" && $FileType != "json" ) {
            echo "<h2 style='color:red;'>Sólo archivos CSV o JSON son permitidos</h2>";
            }
            if (move_uploaded_file($_FILES["resultFile"]["tmp_name"], $target_file)) {
              echo "<h2 style='color:green;'>El archivo ". basename( $_FILES["resultFile"]["name"]). " ha sido subido.</h2>";
            } else {
                var_dump(move_uploaded_file($_FILES["resultFile"]["tmp_name"], $target_file));
              echo "<h2 style='color:red;'>Hubo un errror al subir el archivo de resultados, no se pudo mover a la carpeta.</h2>";
            }
        }



        ?>
            <div class="wrap">
                <h2>Procesar resultados de la votación</h2>
        <?php 
        $results = glob(plugin_dir_path(__FILE__).'src/resultados/*');
        if (empty($results)) {
            echo "<h3>No hay resultados que procesar. Sube el archivo con los resultados en formato json a: ".plugin_dir_path(__FILE__)."resultados<h3>";
        } else {
            foreach ($results as $result_id => $result) {
                $resultadoVotacion = json_decode(file_get_contents($result));
                //DAMOS POR HECHO QUE TODAS PARTES DE VOTACIÓN SON DE LA MISMA VOTACIÓN
                $term = get_term_by('name', $resultadoVotacion->questions[0]->title, 'votacion' );
                $term = get_term_by('id', $term->parent, 'votacion' );
                ?>
                    <div class="card" style="float: left; width: 31%; padding-left:15px; padding-right:15px; box-sizing: border-box; min-height: 1px; position:relative;">
                        <h3>Procesar fichero: <?php echo basename($result) ?> <br>Para la votación <strong><?php echo $term->name ?></strong></h3>
                        <form data-idform="<?php echo $result_id ?>" action="<?php basename ( PGI_PLUGIN_PATH )?>" method="get" accept-charset="utf-8" class="import-form" style="float: right;">
                            <input type="hidden" name="pgi_import_task" value="process_results">
                            <input type="hidden" name="pgi_file_result" value="<?php echo basename($result) ?>">
                            <input type="hidden" value="podemos-gravity-import" name="page">
                            <?php submit_button('Procesar resultado', 'button-large run-import', 'procesar-'.$result_id ) ?>
                        </form>
                    </div>
                <?php
            }
        }
        ?> 
            <div class="card" style="float: left; width: 31%; padding-left:15px; padding-right:15px; box-sizing: border-box; min-height: 1px; position:relative; clear: both">
                <br>
                <hr>       
                <h3>Deseas subir un nuevo fichero de resultados</h3>
                <form action="<?php basename ( PGI_PLUGIN_PATH )?>" method="POST" accept-charset="utf-8" class="import-form" enctype="multipart/form-data">
                    <input type="file" name="resultFile" id="resultFile">
                    <input type="hidden" name="pgi_import_task" value="upload_result">
                    <input type="hidden" value="podemos-gravity-import" name="page">
                    <?php submit_button('Sube tus resultados', 'primary button-large' ) ?>
                </form>
            </div>
        <?php
        ?> 
            </div>
        <?php     
    }

    public static function pgi_content_settings(){
        register_setting('pgi-base-url-group','pgi_base_url');
        register_setting('pgi-base-url-group','pgi_api_key');
        register_setting('pgi-base-url-group','pgi_private_key');
    }

    public static function pintar_formulario_settings(){
        ?>
                <form action="options.php"" method="POST" accept-charset="utf-8">
                    <?php
                        settings_fields('pgi-base-url-group');
                        do_settings_fields('pgi_base_url-group', 'main_section');
                    ?>
                    <input type="hidden" name="page" value="podemos-gravity-import">
                    <label>Base url:</label>
                    <input type="text" name="pgi_base_url" id="pgi-base-url" value="<?php echo get_option('pgi_base_url') ?>">
                    <label>Api key:</label>
                    <input type="text" name="pgi_api_key" id="pgi_api_key" value="<?php echo get_option('pgi_api_key') ?>">
                    <label>Private key:</label>
                    <input type="text" name="pgi_private_key" id="pgi_private_key" value="<?php echo get_option('pgi_private_key') ?>">
                    <?php submit_button('Guardar configuración gravity') ?>
                    <?php if (
                            get_option('pgi_base_url') &&
                            get_option('pgi_api_key') &&
                            get_option('pgi_private_key')
                            ){
                                ?><a href="/wp-admin/admin.php?page=podemos-gravity-import" title="Registra un custom post type">Registra un Custom post type</a><?php
                            } ?>
                </form>
        <?php
    }

    public static function pintar_formulario_consultar_formulario($ifForm = NULL){
        $forms = apply_filters('pgi_forms', []);
        $cats  = apply_filters('pgi_cats', []);
        foreach ($forms as $form_id => $post_type) {
            $inicializado = true;
            if (!empty($cats)) {
                foreach ($cats[$form_id] as $vid => $votacion) {
                    $terms = get_terms ('votacion', array( 'hide_empty' => false, 'meta_key' => 'votacion_id', 'meta_value' => $vid) );
                    if (is_object($terms)) {
                        ?> <h4 style="color:red">Hubo un problema recuperando la categoría de id<?php echo $vid ?></h4> <?php
                    }
                    if (empty($terms)) {
                        $inicializado = false;
                    }
                }
               
            }
            ?>
                <div class="card" style="float: left; width: 31%; padding-left:15px; padding-right:15px; box-sizing: border-box; min-height: 1px; position:relative; min-height: 200px;">
                    <h3>Formulario de Gravity: <?php echo $form_id ?> a Custom Post Type: <?php echo $post_type ?></h3>
                    <?php if ($inicializado): ?>
                        <form data-idform="<?php echo $form_id ?>" action="<?php basename ( PGI_PLUGIN_PATH )?>" method="get" accept-charset="utf-8" class="import-form" style="float: left;">
                            <input type="hidden" name="pgi_id_form" value="<?php echo $form_id ?>">
                            <input type="hidden" name="pgi_import_task" value="start_import">
                            <input type="hidden" name="cpt-name" value="<?php echo $post_type ?>">
                            <input type="hidden" value="podemos-gravity-import" name="page">
                            <input type="checkbox" name="incluir_archivos" value="si" id="incluir_archivos" checked>
                            <label for="incluir_archivos">¿Incluir los archivos en la importación?</label>
                            <?php submit_button('importar', 'primary button-large run-import', 'importar-'.$form_id ) ?>
                        </form>
                        <form data-idform="<?php echo $form_id ?>" action="<?php basename ( PGI_PLUGIN_PATH )?>" method="get" accept-charset="utf-8" class="import-form" style="float: right;">
                            <input type="hidden" name="pgi_id_form" value="<?php echo $form_id ?>">
                            <input type="hidden" name="pgi_import_task" value="delete_all">
                            <input type="hidden" value="podemos-gravity-import" name="page">
                            <?php submit_button('Borrar todo', 'secundary button-large run-import delete_button', 'borrar-'.$form_id ) ?>
                        </form>
                    <?php else: ?>
                        <h4 style="color: red;">Debes inicializar éste formulario antes de importar.</h4>
                    <?php endif ?>
                    <?php 
                        if (!empty($cats[$form_id])) {
                            ?> 
                                <form data-idform="<?php echo $form_id ?>" action="<?php basename ( PGI_PLUGIN_PATH )?>" method="get" accept-charset="utf-8" class="import-form" style="float: right; clear: both; margin-top: -60px;">
                                    <input type="hidden" name="pgi_id_form" value="<?php echo $form_id ?>">
                                    <input type="hidden" name="pgi_import_task" value="initialize">
                                    <input type="hidden" value="podemos-gravity-import" name="page">
                                    <?php submit_button('Inicializar', 'primary button-large run-import', 'inicializar-'.$form_id ) ?>
                                </form>
                            <?php
                        }
                    ?>
                </div>
            <?php
            
        } ?>
            <div class="card" style="float: left; width: 33%; padding-left:15px; padding-right:15px; box-sizing: border-box; min-height: 1px; position:relative;">
                <h3>Recupera un formulario</h3>
                <form action="<?php basename ( PGI_PLUGIN_PATH )?>" method="get" accept-charset="utf-8">
                <label>
                    <label>Mostrar info del formulario:</label>
                    <input type="text" name="pgi_id_form">
                    <input type="hidden" name="pgi_import_task" value="get-form-info">
                    <input type="hidden" value="podemos-gravity-import" name="page">
                    <input type="hidden" value="<?php if($post_type){echo $post_type;} ?>" name="cpt-name">
                    <?php submit_button('Consultar', 'secundary') ?>
                </form>
            </div>
	<?php
    }
    
    public static function pintar_mostrar_formulario($APIWrapper, $idForm){

        $entradas          = $APIWrapper->get_entries($idForm, null, null, array('offset' => 0, 'page_size' => 1000 ));
        $form              = $APIWrapper->get_form($idForm);
        $resultados        = $APIWrapper->get_results($idForm);
        $status_code     = $resultados['status'];
        $total           = 0;
        $total_retrieved = 0;
        $mappingTypes = array(
            'hidden'    => 'text',
            'list'      => 'wysiwyg',
            'fileupload' => 'file'
        );

        if ( $status_code == "complete" ){
            //entries retrieved successfully
            $entries = $entradas['entries'];
            $status  = $status_code;
            $total              = $entradas['total_count'];
            $total_retrieved    = count( $entries );
        }
        ?> 
            <h3>Formulario: <?php echo $form['title'] ?></h3>
            <h4>Número de entradas <?php echo $entradas['total_count'] ?></h4>
            <h3>Resultados</h3>
            <div>Status Code: <?php echo $status ?></div>
            <div>Total Count: <?php echo $total; ?></div>
            <div>Total Retrieved: <?php echo $total_retrieved; ?></div>
            <div>JSON Response:<br/><textarea style="vertical-align: top" cols="125" rows="10"> <?php echo print_r($entradas); ?></textarea></div>
            <div>JSON Form:<br/><textarea style="vertical-align: top" cols="125" rows="10"> <?php echo print_r($form); ?></textarea></div>
            <div>
        <?php
    }

    function import_action_javascript() { 
        $ajax_nonce = wp_create_nonce( "my-special-string" );
        ?>
            <script type="text/javascript" >
            var finish = false;
            jQuery(document).ready(function($) {    
                function runAjax(offset, inputs){
                    var data = {
                        'action': 'import_action',
                        'security': '<?php echo $ajax_nonce; ?>',
                        'data': inputs,
                        'offset': offset
                    };
                    var progressElem = $('#progress');
                    $.ajax({
                        url: ajaxurl,
                        global: false, type: 'POST', 
                        data: data, 
                        cache: false,
                        success: function(data) {
                            $('.wrap').prepend(data['html']);
                            if (data['result'] == 'MORE') {
                                runAjax(data['offset'], inputs);
                            } else if(data['result'] == "FINISH") {
				                $('.wrap').prepend("<h1>Finalizó la importación de datos</h1>");
                                $("#loadingGif").remove();
			                } else if(data['result'] == "DELETED"){
                                $('.wrap').prepend("<h1>Finalizó el borrado</h1>");
                                $("#loadingGif").remove();
                            } else if(data['result'] == "INITIALIZED"){
                                $('.wrap').prepend("<h1>Formulario inicializado... Volviendo a página de importación</h1>");
                                setTimeout(function(){
                                    window.location.reload()
                                }, 2000);
                            }else if(data['result'] == "NO INITIALIZED"){
                                $('.wrap').prepend("<h1 style='color:red'>Error inicializando formulario</h1>");
                                $("#loadingGif").remove();
                            }
                        }
                    });
                }
                $('.run-import').click(function(event) {
                    event.preventDefault();
                    form = $(this).parent().parent();
                    console.log(form);
                    inputs = {};
                    var inputs = JSON.stringify( form.serializeArray() );
                    console.log(inputs);
                    $('.wrap').html('Cargando...<br><div style="position:fixed; top:50%; left:50%" id="loadingGif"><img src="<?php echo plugin_dir_url( dirname( __FILE__ ) ) . 'podemos-gravity-import/img/Loading_icon.gif'; ?>">');
                    runAjax(0, inputs);
                });
                $('.delete_button').click(function(event) {
                    if (confirm("¿seguro que deseas borrar todos los registros?")){
                        $(this).parent().parent().submit();
                    }
                });
            });
            </script> 
        <?php

    } 
}
