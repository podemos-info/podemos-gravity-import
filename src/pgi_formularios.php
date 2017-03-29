<?php
/**
* Importacion genérica de formularios de Gravity Forms 
*/



define('PGI_DEBUG', TRUE);
class pgi_gravity_import
{
    static $gravityWrapper;
    static $startExecution;
    function __construct(){
      add_action( 'wp_ajax_import_action', array('pgi_gravity_import', 'import_action_callback' ));
    }

    function setGW($base_url, $api_key, $private_key){
      self::$gravityWrapper = new GFWebAPIWrapper($base_url, $api_key, $private_key);
    }

    public function import_action_callback() {  
      self::$startExecution = microtime(true);
      if ($_POST['offset'] == 0) {
        $_COOKIE['first_run_time'] == self::$startExecution;
      }
      check_ajax_referer( 'my-special-string', 'security' );
      $data = $_POST ;
      $data = json_decode(stripslashes($data['data']), true);
      $associativeData = array();
      foreach ($data as $value) {
          $associativeData[$value['name']] = $value['value'];
      }
      self::run_import_task($associativeData, $_POST['offset']);
      wp_die();
    }

    public function run_import_task($data = null, $offset = null) {
      if (empty($data)) {
          $data = $_GET;
      }
      if ($data['page'] == "podemos-gravity-import") {
        if ($data['pgi_import_task'])
        {
          $sw = $data['pgi_import_task'];
          $base_url = get_option('pgi_base_url');
          $api_key = get_option('pgi_api_key');
          $private_key = get_option('pgi_private_key');
          $idForm = isset($data['pgi_id_form']) ? (int) $data['pgi_id_form'] : NULL;
          self::setGw($base_url, $api_key, $private_key);
          $api = self::$gravityWrapper;
          switch ($sw) {
            case "process_results";
              $response = pgi_process_results($data['pgi_file_result']);
              wp_send_json($response);
              wp_die();
              break;
            case "get-form-info":
            case "get-form-cpt-class":
              if ($idForm !== NULL) {
                adminPage::pintar_mostrar_formulario($api, $idForm);
              } else {
                ?>
                  <div class="error notice">
                      <p><?php _e( 'El id del formulario no es un entero', 'pgi-import' ); ?></p>
                  </div>
                <?php
              }
              echo "<hr><hr><hr><hr><hr><hr><hr><hr><h2>¿Quieres probar con otro?</h2>";
              adminPage::pintar_formulario_consultar_formulario();
              break;
            case "start_import":
              if ( (isset($data["cpt-name"])) && post_type_exists( strtolower($data["cpt-name"]))  ) {
                $response = self::start_import($idForm, $data, $offset);
                wp_send_json($response);
                wp_die();
              } else {
                ?>
                  <div class="error notice">
                      <p><?php _e( '¿Has seguido los pasos correctos? ¿Has definido ya tu Custom Post Type?', 'pgi-import' ); ?></p>
                  </div>
                <?php
                adminPage::pintar_formulario_consultar_formulario();
              }
              break;
            case "delete_all":
              $response['html'] = '';
              $response['result'] = "DELETED";
              self::borrar_todos($idForm, $response);
              wp_send_json($response);
              wp_die();
            default:
            case 'initialize':
              $cats   = apply_filters('pgi_cats', []);
              $response = pgi_votacion_categorias($cats[$idForm]);
              wp_send_json($response);
              wp_die();
              break;
              ?>
                <div class="error notice">
                    <p><?php _e( 'Algo fue mal detectando el import task', 'pgi-import' ); ?></p>
                </div>
              <?php
              break;
          }
        } else {
            adminPage::pintar_formulario_consultar_formulario();
        }
      }
        
    }

    public function start_import($idForm, $data, $offset){
      $response['html'] = ''; 
      $api = self::$gravityWrapper;
      $page_size = 20;
      $entradas = $api->get_entries($idForm, null, null, array('offset' => $offset, 'page_size' => $page_size ));
      if (is_object($entradas)) {
        $response['html'] .= "Parece que hubo un error recuperando los datos de gravity";
        return $response;
      }
      if (PGI_DEBUG) $response['html'] .= "<p>recuento de entradas ".count($entradas['entries'])."</p>";
      if ( count($entradas['entries'])==0) {
        self::actualizar_borrados($idForm, $response);
        $total_time_elapsed = microtime(true) - $_COOKIE['first_run_time'];
        unset($_COOKIE['first_run_time']);
        $response['html'] .= "<div style='position:fixed;right:0;bottom:20px; color:red'>Tiempo de ejecución total".$total_time_elapsed;
        $response['result'] = "FINISH";
        return $response;
      }
      $form = $api->get_form($idForm);
      $total = $entradas['total_count'];
      $entradas = apply_filters( "pgi_pre_import_{$idForm}", $entradas );
      if (is_string($entradas)) {
        # Si entradas es un string el filtro pre_import nos esta dando un feedback de un error.
        $response['html'] = $entradas;
        return $response;
      }
      $response['html'] = "";
      $response['html'] .= "<p>Importando ".$total." entradas<br>Entrada ".($offset+1)." al ".($offset+$page_size)."<br></p>";
      $response['html'] .= "<p></p>";
      
      $i = 0;
      foreach ( $entradas['entries'] as $entrada ){
        $i++; if (PGI_DEBUG && $i>5) break;
        $response['html'] .= "<br><br>Importando entrada ".($i+$offset+1)."<br>";
        $entrada['post-type'] = $data["cpt-name"];
        $entrada['form-id'] = $idForm;
        $entrada['original-id'] = $entrada['id'];
        $incluirArchivos = $data['incluir_archivos'];
        $entradas_filtradas = apply_filters("pgi_pre_process_$idForm", [ $entrada ] );
        foreach ($entradas_filtradas as $entry) {
          $post_id = self::programmatically_create_post($idForm, $entry, $entry['post-type'], $incluirArchivos, $response);
          if( -1 == $post_id || -2 == $post_id ) {
            $response['html'] .= "<p>Algo fue mal creado el post en la repetición id. ".$i.".: id de la entrada: ".$entry['id']."</p>";
            ?>
            <div class="error notice"><p><?php _e( 'Algo fue mal creado el post del registro id. '.$i, 'pgi-import' ); ?></p></div>
            <?php
          } else {
            if (PGI_DEBUG) $response['html'] .= "<p>Post con $post_id ha sido creado o actualizado.</p>";
            do_action("pgi_post_process_{$entry['form-id']}", $entry, $post_id);
          }
        }
        do_action( "pgi_post_import_{$idForm}", $entradas );
      }
      $response['result'] = "MORE";
      $response['offset'] = $offset+$page_size;
      $time_elapsed_secs_this_page = microtime(true) - self::$startExecution;
      $response['html'] .= "<div style='position:fixed;right:0;top:".(50+$offset)."px; color:red'>Tiempo de ejecución para página ".($response['offset']/$page_size).": ".$time_elapsed_secs_this_page;
      return $response;
    }

    /**
     * A function used to programmatically create a post in WordPress. The slug, author ID, and title
     * are defined within the context of the function.
     *
     * @returns -1 if the post was never created, -2 if a post with the same title exists, or the ID
     *          of the post if successful.
     */

    public static function programmatically_create_post($id_formulario_original, $entrada, $post_type, $incluirArchivos = false, &$response) {
      $id_formulario = $entrada['form-id'];
      $mapper = apply_filters( "pgi_mapper_{$id_formulario}", [] );
      if (PGI_DEBUG) $response['html'] .= "<p>Info de la entrada: {$post_type} - {$entrada['id']}</p>";
      $posts = get_posts(['post_type' => $post_type, 'posts_per_page' => 1, 'post_status' => array('any','trash'), 'meta_key' => 'gravity_entry_id', 'meta_value' => $entrada['id']]);
      if (empty($posts) and empty($trashPosts)) {
          $old_post = NULL;
      } else {
          $old_post = $posts[0];
      }
      
      // Initialize the page ID to -1. This indicates no action has been taken.
      $post_id = -1;

      // Setup the author, slug, and title for the post
      $author_id = 1;
      $title = apply_filters( "pgi_title_{$id_formulario}", $entrada['id'], $entrada );
      $response['html'] .= "Entrada {$entrada['id']}: $title<br>";
      $content = apply_filters( "pgi_content_{$id_formulario}", "", $entrada );
      $slug = self::slug($title);
      $postAttr = array(
          'comment_status'    =>  'closed',
          'post_author'       =>  $author_id,
          'post_name'         =>  $slug,
          'post_title'        =>  $title,
          'post_status'       =>  'publish',
          'post_type'         =>  $post_type
      );
      /* Es posible que algunos formularios. Como en este caso listas_vistalegre 2 dejen el campo content vacio. esto da un error no le ponemos attributo content si content esta vacio */
      if (!empty($content)) {
        $postAttr['post_content'] = $content;
      }
      if ($old_post != NULL) {
          $postAttr['ID'] = $posts[0]->ID;
          $post_id = wp_update_post($postAttr);
          $response['html'] .= "Post de tipo ".$post_type." actualizado ".$post_id."<br>";
      } else {
          $post_id = wp_insert_post($postAttr);
          $response['html'] .= "Post de tipo ".$post_type." guardado ".$post_id."<br>";
      }
      if ($post_id > 0 && $post_id != -1) {
        update_post_meta( $post_id, 'gravity_entry_original_id', $entrada['original-id'] );
        update_post_meta( $post_id, 'gravity_entry_id', $entrada['id'] );  //Renombrado para evitar confusiones /* OJO PODRIA DAR PROBLEMAS CON REGISTROS YA IMPORTADOS SI NO SE BORRAN Y SE VUELVEN A CREAR TODOS JUNTOS. IGUAL ES MEJOR DEJAR ESTE CON EL NOMBRE QUE TENIA Y BUSCAR OTRO NOMBRE AL DE ABAJO. */
        update_post_meta( $post_id, 'gravity_form_id', $id_formulario_original ); //Para poder iterar en el borrado sólo sobre los post de un determinado formulario y evitar que se borre  el resto de entradas, por ejemplo en opciones que recive importacion de dos formularios .
        //$fileHashes = array();  //TODO No implementado todavía.
        foreach ($mapper as $keyField => $info) {
          $valueField = $entrada[$keyField];
          $update = TRUE;
          $is_thumbnail = $info['type'] == 'thumbnail';
          if (
                ($info['type'] == 'file' || $is_thumbnail) && 
                !empty($valueField) &&
                $incluirArchivos == "si"
              ) {
            $encodedUrl = urlencode($valueField);
            $fixedEncodedUrl = str_replace(['%2F', '%3A'], ['/', ':'], $encodedUrl);
            //Si es un nuevo registro o el fichero a cambiado
            $startDownload = microtime(true);
            $time_elapsed_secs_this_file = microtime(true) - $startDownload;
            $url = $fixedEncodedUrl;
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            $file = curl_exec($curl);
            curl_close($curl);
            $md5FileHash = md5($file);
            if (PGI_DEBUG) $response['html'] .= "<p style='color:blue'>Tiempo de ejecución para descarga de archivo: ".$time_elapsed_secs_this_file."<p>";
            if ($file===FALSE) {
              $response['html'] .= "<p>Hubo un problema al descargarse el archivo ".$fixedEncodedUrl."</p>";
            }
            if ($old_post != NULL) {
              $haCambiado = get_post_meta($post_id, $info['name']."_hash", true) != $md5FileHash;
              if ($haCambiado) {
                if (PGI_DEBUG) $response['html'] .= "<p>El archivo remoto ha cambiado</p>";
                $attachment_id = $is_thumbnail ? get_post_thumbnail_id($post_id) : get_post_meta($post_id, $info['name'], true);
                if (PGI_DEBUG) $response['html'] .= "<p>Se borra el attachment al post y la imagen con wp_delete_post</p>";
                wp_delete_attachment( $attachment_id );
                if(!wp_delete_post( $attachment_id, true )){
                  $response['html'] .= "<p>Hubo un error borrando la imagen ".basename($valueField)."</p>";
                }
              } else {
                if (PGI_DEBUG) $response['html'] .= "<p>El archivo remoto no ha cambiado</p>";
                $haCambiado = false;
                $update = false;
              }
            }
            if ($old_post == NULL || $haCambiado) {
              
              $filesize = strlen($file);
              $upload = wp_upload_bits(basename($valueField), null, $file);
              if(isset($upload['error']) && $upload['error'] != 0) {
                $response['html'] .= "<p>Ha habido un error subiendo el archivo: ". $upload['error']."</p>";
                wp_die('There was an error uploading your file. The error is: ' . $upload['error']);
              } else {
                // The wp_insert_attachment function needs the literal system path, which was passed back from wp_handle_upload
                $file_name_and_location = $upload['file'];
                // Set up options array to add this file as an attachment
                $attachment = array(
                  'post_mime_type' => $upload["type"],
                  'post_title' => 'Uploaded ' . addslashes(basename($valueField)),
                  'post_content' => '',
                  'post_status' => 'inherit'
                );
                $attach_id = wp_insert_attachment( $attachment, $file_name_and_location );
                $attach_data = wp_generate_attachment_metadata( $attach_id, $file_name_and_location );
                wp_update_attachment_metadata( $attach_id,  $attach_data );
                update_post_meta( $attach_id, 'gravity_entry_original_id', $entrada['original-id'] );
                update_post_meta( $attach_id, 'gravity_form_id', $id_formulario_original );

                // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
                $valueField = $attach_id;
                $response['html'] .= "File url: ".$fixedEncodedUrl."<br>";
                update_post_meta( $post_id, $info['name']."_hash", $md5FileHash );
                //$fileHashes[] = $md5FileHash; TODO NO IMPLEMENTADO TODAVIA
                if (PGI_DEBUG) $response['html'] .= "<p>Nuevo archivo subido y meta datos actualizados</p>";
              } // end if/else
            }
          }
          if($update){
            $keyFieldFixCheckbox = str_replace('.', '_', $keyField);

            if ($is_thumbnail) {
              set_post_thumbnail($post_id, $valueField);
              if (PGI_DEBUG) $response['html'] .= "<p>Thumbnail establecido: $valueField</p>";
            } else {
              $valueField = apply_filters("pgi_campo_{$id_formulario}_{$keyFieldFixCheckbox}", $valueField);
              update_post_meta( $post_id, $info['name'], $valueField );
              if (PGI_DEBUG) $response['html'] .= "<p>Actualiza metadato para {$info['name']}: $valueField</p>";
            }
          }
        }

        /*  TODO LAS ENTRADAS FUERON MODIFICADAS POR LOS FILTROS ESTO NO FUNCIONARÁ ADEMAS ES PELIGROSO PROBAR ACTUALIZAR UNA ENTRADA EN UN FORMULARIO DE PRODUCCIÓN */
       // if (!empty($fileHashes)) {
         // $entrada['54'] = json_encode($fileHashes);
          // $api->update_entry($entrada['id'], $entrada); Guardamos en gravity los hashes de los archivos para evitar descargarnos el fichero si no se actualizo.
       // }
        //Check if post is in trash
        if ($entrada['status'] == 'trash') {
          if (PGI_DEBUG) $response['html'] .= "</p>Este post se envía a la papelera</p>";
          wp_trash_post($post_id);
        } else {
          wp_untrash_post($post_id);
        }
      } else {
          $response['html'] .= "Fallo en el post<br>";
      }
      return $post_id;
    } // end programmatically_create_post

    public static function slug($str)
    {
        $str = strtolower(trim($str));
        $table = array(
            'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
            'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
            'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
            'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
            'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r',
        );
        $str = strtr($str, $table);
        $str = preg_replace('/[^a-z0-9-]/', '_', $str);
        $str = preg_replace('/-+/', "_", $str);
        return $str;
    }

  public static function actualizar_borrados($formulario_id, &$response){
    $posts = get_posts(['post_type' => 'any',
                        'post_status' => ['any','trash'], 
                        'meta_key' => 'gravity_form_id', 'meta_value' => $formulario_id,
                        'posts_per_page' => 10000
                       ]);
    $api = self::$gravityWrapper;
    $entradas = $api->get_entries($formulario_id, null, null, array('offset' => 0, 'page_size' => 10000 ));

    if (is_object($entradas)) {
      $response['html'] .= "Parece que hubo un error recuperando los datos de gravity";
      return;
    }

    $entradas_id = array_map(function($entrada) {return $entrada['id'];}, $entradas['entries']);
    if (PGI_DEBUG) error_log(implode(" ", $entradas_id));
    $entradas_id = array_combine($entradas_id, $entradas_id);

    $response['html'] .= "<p>Comprobando posts que son necesarios eliminar </p>";

    foreach ($posts as $post) {
      $post_id = $post->ID;
      if (get_post_meta($post_id, 'gravity_form_id', true)!=$formulario_id) continue;
      
      $original_id = get_post_meta($post_id, 'gravity_entry_original_id', true);

      if (PGI_DEBUG) error_log("$post_id $original_id");
      if (array_key_exists($original_id, $entradas_id)) continue;
      if (PGI_DEBUG) error_log("NO EXISTE");


      if ($post->post_type == 'attachment') {
        wp_delete_attachment( $post_id );
        if (PGI_DEBUG) $response['html'] .= "<p>Se borra el attachment </p>";
      } else {
        $thumbnail_attachment_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_attachment_id){
          wp_delete_attachment( $thumbnail_attachment_id );
        }
        $response['html'] .= "<p>Se elimino post de id: $post_id</p>";
        wp_delete_post( $post_id );
      }
    }
  }

  public static function borrar_todos($formulario_id, &$response){
    $posts = get_posts(['post_type' => 'any',
                        'post_status' => ['any','trash'], 
                        'meta_key' => 'gravity_form_id', 'meta_value' => $formulario_id,
                        'posts_per_page' => 10000
                       ]);
    foreach ($posts as $post) {
      $post_id = $post->ID;
      if (get_post_meta($post_id, 'gravity_form_id', true)!=$formulario_id) continue;

      if ($post->post_type == 'attachment') {
        wp_delete_attachment( $post_id );
        if (PGI_DEBUG) $response['html'] .= "<p>Se borra el attachment </p>";
      } else {
        $thumbnail_attachment_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_attachment_id){
          wp_delete_attachment( $thumbnail_attachment_id );
        }
        wp_delete_post( $post_id );
      }
    }
  }

  public function get_post_by_title($page_title, $post_type ='post' , $output = OBJECT) {
   /* global $wpdb;
        $post = $wpdb->get_var( $wpdb->prepare( "
          SELECT ID 
          FROM $wpdb->posts 
            WHERE post_title = %s 
            AND post_type= %s", $page_title, $post_type));
        if ( $post )
            return get_post($post, $output);

    return null;*/
  }
}

