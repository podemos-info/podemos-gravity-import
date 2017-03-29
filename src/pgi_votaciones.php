<?php
function pgi_categoria_votacion(){
  // Add new taxonomy, make it hierarchical (like categories)
  $labels = array(
    'name'                  => _x( 'Votaciones', 'taxonomy general name' ),
    'singular_name'         => _x( 'Tipo de votación', 'taxonomy singular name' ),
    'add_new'               => _x( 'Añadir nueva votación', 'Exhibition Type'),
    'add_new_item'          => __( 'Añadir nueva votación' ),
    'edit_item'             => __( 'Editar votación' ),
    'new_item'              => __( 'Nueva votación item' ),
    'view_item'             => __( 'ver votación' ),
    'search_items'          => __( 'Buscar votaciones' ),
    'not_found'             => __( 'votación no encontrada' ),
    'not_found_in_trash'    => __( 'No se encontró votación en la papelera' )
  );

  $args = array(
    'labels'            => $labels,
    'singular_label'    => __('Votacion Type'),
    'public'            => true,
    'show_ui'           => true,
    'hierarchical'      => true,
    'show_tagcloud'     => false,
    'rewrite'           => array('slug' => 'votacion', 'with_front' => false ),
    'capabilities' => array(
    'manage_terms' => 'manage_options',
    'edit_terms' => '',
    'delete_terms' => '',
    'assign_terms' => 'manage_options'
  ),
  );
  register_taxonomy( 'votacion', 'lista', $args );
  register_taxonomy_for_object_type( 'votacion', 'opcion' );
}
add_action( "init", "pgi_categoria_votacion", 0);

function add_votacion_menu() {
  $link = 'edit-tags.php?taxonomy=votacion&post_type=opcion';
  add_menu_page('Votaciones', 'Votaciones', 'manage_options', $link, '', '
dashicons-forms', 10);
  add_submenu_page( $link, "Candidaturas", "Candidaturas", 'manage_options', $link);
  add_submenu_page( $link, "Listas", "Listas", 'manage_options', str_replace("opcion", "lista", $link));
  $terms = get_terms([ 'taxonomy' => 'votacion', 'parent' => 0] );
  foreach ($terms as $term) {
    add_submenu_page( $link, $term->name, $term->name, 'manage_options', "edit.php?votacion=$term->slug&post_type=opcion");    
  } 
}
add_action('admin_menu', 'add_votacion_menu');

function pgi_votacion_categorias($votaciones) {
  $response['html'] = '';
  foreach( $votaciones as $vid => $votacion ){
    //see if we already have populated any terms
    $terms = get_terms ('votacion', array( 'hide_empty' => false, 'meta_key' => 'votacion_id', 'meta_value' => $vid) );
    if(count($terms)==0) {
      $ret = wp_insert_term( $votacion['name'], 'votacion', array( 'slug' => $votacion['slug'] ) );
      if (is_object($ret)) {
        $response['html'] = "Hubo un problema al crear la categoría ".$votacion['name'].". Inicialización aboratada.";
        $response['result'] = "NO INITIALIZED";
        return $response;
      }
      $term_id = $ret['term_id'];
    } else {
      $term_id = $terms[0]->term_id;
      if ($terms[0]->name != $votacion['name'] || $terms[0]->slug != $votacion['slug'])
        wp_update_term($term_id, 'votacion', [ 'name' => $votacion['name'], 'slug' => $votacion['slug'] ]);
    }
    update_term_meta ($term_id, 'votacion_id', $vid );
    $i = 0;
    foreach ($votacion['partes'] as $pvid => $parte_votacion ) {
      $pterms = get_terms ('votacion', array( 'hide_empty' => false, 'meta_key' => 'votacion_id', 'meta_value' => $pvid) );
      if(count($pterms)==0) {
        $pret = wp_insert_term( $parte_votacion['name'], 'votacion', array( 'slug' => $parte_votacion['slug'], 'parent' => $term_id ) );
        if (is_object($pret)) {
          $response['html'] = "Hubo un problema al crear la sub-categoría ".$parte_votacion['name'].". Inicialización aboratada.";
          $response['result'] = "NO INITIALIZED";
          return $response;
        }
        $pterm_id = $pret['term_id'];
      } else {
        $pterm_id = $pterms[0]->term_id;
      }

      update_term_meta ($pterm_id, 'votacion_id', $pvid );
      update_term_meta ($pterm_id, 'votacion_orden', $i++ );
      update_term_meta ($pterm_id, 'term_order', ["orderby"=>"custom", "order"=>"asc", "postmeta"=>"", "postmetatype"=>"CHAR"]);
    }
  }
  $response['result'] = "INITIALIZED";
  return $response;
}

function pgi_process_results($file){
  
  if (pathinfo($file, PATHINFO_EXTENSION) == 'csv') {

    $csv= file_get_contents(plugin_dir_path(__FILE__).'resultados/'.$file);
    $arrayLines = explode("\n", $csv);
    $arrayFinal = array();
    for ($i=0; $i < count($arrayLines); $i++) { 
      if (empty(trim($arrayLines[$i]))) {
          $tab = true;
          $brakLine = $i;
          break;
      }
      $arrayCSV = str_getcsv($arrayLines[$i], "\t");
      if ($arrayCSV[0] == 'title') {
        $arrayFinal['questions'][0]['title'] = $arrayCSV[1];
      }
      if ($arrayCSV[0] == 'num_winners') {
        $arrayFinal['questions'][0]['num_winners'] = $arrayCSV[1];
      }
      if ($arrayCSV[0] == 'totals') {
        $arrayFinal['questions'][0]['totals']['valid_votes'] = $arrayCSV[2];
        $arrayFinal['questions'][0]['totals']['blank_votes'] = $arrayCSV[4];
        $arrayFinal['questions'][0]['totals']['null_votes'] = $arrayCSV[6];
      }  
    }
    $j = 0;
    for ($i=$brakLine+2; $i < count($arrayLines); $i++) {
      if (empty($arrayLines[$i])) {
        continue;
      }
      $arrayCSV = str_getcsv($arrayLines[$i], "\t"); 
      $arrayFinal['questions'][0]['answers'][$j]['ID'] = $arrayCSV[0];
      $arrayFinal['questions'][0]['answers'][$j]['text'] = $arrayCSV[1];
      $arrayFinal['questions'][0]['answers'][$j]['total_count'] = $arrayCSV[2];
      $arrayFinal['questions'][0]['answers'][$j]['position'] = $arrayCSV[3]; 
      if (!isset($arrayCSV[3])) {
        $arrayFinal['questions'][0]['answers'][$j]['winner_position'] = $arrayCSV[3];
      }
      $j++;
    }

   
    $json = json_encode($arrayFinal); 
    $resultadoVotacion = json_decode($json);
  } else {
    $result = plugin_dir_path(__FILE__).'resultados/'.$file;
    $resultadoVotacion = json_decode(file_get_contents($result));
  }


  $agora_voting_id = substr(explode(".",$result)[0],0,-1);// Soponemos que el archivo tiene el id correcto podría haber un despiste.
  foreach ($resultadoVotacion->questions as $key => $subcategory) {
    $response['html'] .= "<br><h1>Parte de votación: ".$subcategory->title."</h1>";
    $term = apply_filters("pgi_process_results_voting_{$agora_voting_id}", null, $subcategory);
    if (empty($term)) {
      $term = get_term_by ('name', $subcategory->title, 'votacion' );
    }
    if (empty($term)) {
      $response['html'] = '<br><span  style="color:red; font-weight:bolder">No existe la categoría'.$subcategory->title." la importación no puede continuar</span>";
      $response['result'] = "FINISH";
      return $response;
    }
    $valid_votes = $subcategory->totals->valid_votes;
    update_term_meta ($term->term_id, 'votacion_numero_ganadores', $subcategory->num_winners );
    update_term_meta ($term->term_id, 'votacion_votos_blanco', $subcategory->totals->blank_votes );
    update_term_meta ($term->term_id, 'votacion_votos_nulo', $subcategory->totals->null_votes );
    update_term_meta ($term->term_id, 'votacion_votos_validos', $valid_votes );

    $votacion_id = get_term_meta($term->term_id, 'votacion_id', true );
    $voting_part_max_points = apply_filters("pgi_process_results_voting_{$votacion_id}_max_points", 1);
    foreach ($subcategory->answers as $i => $answer) {
      $pos = $i;
      if(isset($answer->position)){
        $pos = (int) $answer->position - 1;
      }
      $opciones = apply_filters("pgi_process_results_opcion_{$votacion_id}", null, $term, $answer);
      if ($opciones==NULL) {
        $opciones = new WP_Query( ['post_type' => 'opcion', 'title' => $answer->text, 'posts_per_page' => 10000, 'tax_query'=> [['taxonomy'=>'votacion', 'field'=>'term_id', 'terms'=>$term->term_id]] ]);
      }
      $response['html'] .= "<ul>";
      if (empty($opciones->posts)) {
          $response['html'] .= '<br><span  style="color:red; font-weight:bolder">El candidato:'.$answer->text." con en la categoría ".$term->name." no ha podido ser encontrado.</span>";
        }
      foreach ($opciones->posts as $opcion) {
        $response['html'] .= "<br>id: ".$opcion->ID;
        $response['html'] .= "<br>pos: ".$pos;
        $response['html'] .= "<br>total count: ".$answer->total_count;
        $a = update_post_meta( $opcion->ID, 'opcion_posicion_agora', $pos+1 );
        if (is_object($a)) {
          var_dump($a);
        }
        if(!$a){
          add_term_meta( $opcion->ID, 'opcion_posicion_agora', $pos+1 );
        }
        update_post_meta( $opcion->ID, '_sort_' . $term->term_id, $pos );
        $response['html'] .= "<br>'_sort_" . $term->term_id.": ".$pos;
        update_post_meta( $opcion->ID, 'opcion_puntos', $answer->total_count);
        $response['html'] .= "<br>max points: ".$voting_part_max_points;
        $response['html'] .= "<br>valid votes: ".$valid_votes;
        update_post_meta( $opcion->ID, 'opcion_porcentaje', round(100*$answer->total_count / $voting_part_max_points / $valid_votes, 2));
        if (!empty($answer->voters_by_position)) {
          update_post_meta( $opcion->ID, 'opcion_votos_por_posicion', json_encode($answer->voters_by_position));
        }
        $response['html'] .= "<li> La opción '<strong>".$opcion->post_title."</strong>' (".implode(", ",wp_get_post_terms($opcion->ID, 'votacion', ["fields" => "names"])).") ha sido actualizado</li>";

      }
      $response['html'] .= "</ul>";
    }
    $response['html'] .= "<hr>";
  }                        
  $response['html'] = "<h1>Resultados procesados</h!>. ".$response['html'];
  $response['result'] = "FINISH";
  return $response;
}


// [atributo-opcion atributo="un nombre"]
function opcion_atributo_func( $atts ) {
  $term = get_term_by ('name', $atts['parte_votacion'], 'votacion' );
  $opciones = new WP_Query( [
    'post_type' => 'opcion', 
    'posts_per_page' => 1,
    'tax_query'=> [
    	['taxonomy'=>'votacion', 'field'=>'name', 'terms'=>$atts['parte_votacion']]
  	],
    'meta_key' => '_sort_'.$term->term_id,
    'orderby' => 'meta_value_num'
 ]);
  if( strpos($atts['dame'], "opcion_") !== false){ //suponemos que es meta
  	return get_post_meta( $opciones->posts[0]->ID, $atts['dame'], true );
  }
  if($atts['dame'] == "thumbnail"){
    return get_the_post_thumbnail_url( $opciones->posts[0]->ID, array(150,104) );
  }
  if( strpos($atts['parte_votacion'], "Documento ") !== false){
  	$megaequipo = get_post_meta( $opciones->posts[0]->ID, 'opcion_megaequipo', true );
    if(!empty($megaequipo)){
    	return $megaequipo;
    }
  }
	$property_name = $atts['dame'];  
  return $opciones->posts[0]->{$property_name};
}
add_shortcode( 'atributo-opcion', 'opcion_atributo_func' );

// [atributo-votacion atributo="un nombre"]
function votacion_atributo_func( $atts ) {
  $term = get_term_by ('name', $atts['parte_votacion'], 'votacion' );
  if($atts['votos_totales']){
  	return 
      intval(get_term_meta($term->term_id, 'votacion_votos_validos', true )) 
      +
      intval(get_term_meta($term->term_id, 'votacion_votos_nulo', true )) 
      +
      intval(get_term_meta($term->term_id, 'votacion_votos_blanco', true ));
  }
  return get_term_meta($term->term_id, $atts['dame'], true );
}
add_shortcode( 'atributo-votacion', 'votacion_atributo_func' );

require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'pgi_opciones.php';
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'pgi_listas.php';


