<?php
function pgi_votacion_opcion_categoria($entrada, $post_id) {
  $vid = $entrada['votacion'];
  $terms = get_terms('votacion', array( 'hide_empty' => false, 'meta_key' => 'votacion_id', 'meta_value' => $vid, 'fields'=>'ids') );
  wp_set_object_terms( $post_id, $terms, 'votacion', false );
}
function pgi_votacion_formulario_opciones($formulario) {
  add_action("pgi_post_process_$formulario", 'pgi_votacion_opcion_categoria', 10, 2);
}

function pgi_votacion_registrar_opcion() {
  $supports = [ 'title', 'editor', 'thumbnail' ];
  if(function_exists("register_field_group"))
  {
    $definiciones = apply_filters('definicion_opcion', []);

    foreach ($definiciones as $definicion) {
      //var_dump($definicion);
	    register_field_group( [
	      'id' => 'pgi_opcion_'.$definicion["id"],
	      'title' => 'Opciones '.$definicion["nombre"],
	      'fields' => $definicion["campos"],
	      'location' => [[[ 'param' => 'post_type', 'operator' => '==', 'value' => 'opcion', 'order_no' => 2, 'group_no' => 2 ]]],
	      'options' => [ 'position' => 'normal', 'layout' => 'no_box', 'hide_on_screen' => []],
	      'menu_order' => 2,
	    ]);
    }
  } else {
    $supports []= "custom-fields";
  }
  
  register_post_type('opcion', [
    'labels' => [
        'name' => __('Opciones', 'pgi-import'),
        'singular_name' => __('Opción', 'pgi-import'),
        'add_new' => __('Añadir nuevo', 'pgi-import'),
        'add_new_item' => __('Añadir nueva opción', 'pgi-import'),
        'edit' => __('Editar', 'pgi-import'),
        'edit_item' => __('Editar opción', 'pgi-import'),
        'new_item' => __('Nueva opción', 'pgi-import'),
        'view' => __('Ver', 'pgi-import'),
        'view_item' => __('Ver opción', 'pgi-import'),
        'search_items' => __('Buscar opción', 'pgi-import'),
        'not_found' => __('No se han encontrado opciones', 'pgi-import'),
        'not_found_in_trash' => __('No se han encontrado opciones en la papelera', 'pgi-import')
    ],
    'public' => true,
    'hierarchical' => false,
    'has_archive' => false,
    'supports' => $supports,
    'can_export' => true,
    'taxonomies' => [  ], 
    'rewrite' => [ 'slug' => 'candidaturas', 'with_front' => false ],
    'menu_icon' => 'dashicons-editor-ul',
    'taxonomies' => ['votacion'],
    'show_in_menu' => false,
  ]);
}
add_action( 'init' , 'pgi_votacion_registrar_opcion');

function opcion_type_columns( $taxonomies ) {
    $taxonomies[] = 'votacion';
    return $taxonomies;
}
add_filter( 'manage_taxonomies_for_opcion_columns', 'opcion_type_columns' );

//Opciones que se repiten que distintos formularios que importan a opciones
function pgi_definicion_opciones($definiciones) {
  $definiciones[]= 
    [ 'id' => 'general', 'nombre' => 'General', 'campos' => [
      [ 'key' => 'opcion_video', 'label' => 'Video', 'name' => 'opcion_video', 'type' => 'text'] , 
      [ 'key' => 'opcion_motivacion', 'label' => 'Motivación', 'name' => 'opcion_motivacion', 'type' => 'textarea'] , 
      [ 'key' => 'opcion_sexo', 'label' => 'Sexo', 'name' => 'opcion_sexo', 'type' => 'radio', 'choices' => array( '0' => 'Hombre', '1' => 'Mujer') ],
    ]];
  return $definiciones;
}
add_filter('definicion_opcion', 'pgi_definicion_opciones');