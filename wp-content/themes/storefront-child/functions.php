<?php 
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
function theme_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );

}

/**
 * Permettre l'upload de fichiers SVG dans la médiathèque
 */
function twentytwentyfour_child_allow_svg_upload($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'twentytwentyfour_child_allow_svg_upload');

/**
 * Corriger l'affichage des SVG dans la médiathèque
 */
function twentytwentyfour_child_fix_svg_display($response, $attachment, $meta) {
    if ($response['type'] === 'image' && $response['subtype'] === 'svg+xml') {
        $response['image'] = array(
            'src' => $response['url'],
            'width' => 300,
            'height' => 300,
        );
        $response['thumb'] = array(
            'src' => $response['url'],
            'width' => 150,
            'height' => 150,
        );
        $response['sizes'] = array(
            'full' => array(
                'url' => $response['url'],
                'width' => 300,
                'height' => 300,
                'orientation' => 'landscape',
            )
        );
    }
    return $response;
}
add_filter('wp_prepare_attachment_for_js', 'twentytwentyfour_child_fix_svg_display', 10, 3);

/**
 * Sécuriser les fichiers SVG uploadés
 */
function twentytwentyfour_child_sanitize_svg($file) {
    // Vérifier si c'est un fichier SVG
    if ($file['type'] !== 'image/svg+xml') {
        return $file;
    }
    
    // Lire le contenu du fichier
    $svg_content = file_get_contents($file['tmp_name']);
    
    if ($svg_content === false) {
        $file['error'] = __('Impossible de lire le fichier SVG.', 'twentytwentyfour-child');
        return $file;
    }
    
    // Vérifier si c'est un SVG valide
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    
    if (!$dom->loadXML($svg_content)) {
        $file['error'] = __('Le fichier SVG n\'est pas valide.', 'twentytwentyfour-child');
        return $file;
    }
    
    // Nettoyer le SVG des éléments potentiellement dangereux
    $svg_content = twentytwentyfour_child_clean_svg($svg_content);
    
    if ($svg_content === false) {
        $file['error'] = __('Le fichier SVG contient des éléments non autorisés.', 'twentytwentyfour-child');
        return $file;
    }
    
    // Réécrire le fichier nettoyé
    file_put_contents($file['tmp_name'], $svg_content);
    
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'twentytwentyfour_child_sanitize_svg');

/**
 * Nettoyer le contenu SVG des éléments dangereux
 */
function twentytwentyfour_child_clean_svg($svg_content) {
    // Éléments et attributs interdits pour la sécurité
    $forbidden_elements = array(
        'script',
        'object',
        'embed',
        'iframe',
        'link',
        'meta',
        'form',
        'input',
        'button',
        'textarea',
    );
    
    $forbidden_attributes = array(
        'onload',
        'onclick',
        'onmouseover',
        'onerror',
        'javascript:',
        'vbscript:',
        'data:',
        'base64',
    );
    
    // Vérifier les éléments interdits
    foreach ($forbidden_elements as $element) {
        if (stripos($svg_content, '<' . $element) !== false) {
            return false;
        }
    }
    
    // Vérifier les attributs interdits
    foreach ($forbidden_attributes as $attribute) {
        if (stripos($svg_content, $attribute) !== false) {
            return false;
        }
    }
    
    return $svg_content;
}

/**
 * Ajouter les dimensions aux métadonnées SVG
 */
function twentytwentyfour_child_svg_metadata($metadata, $file, $filesize) {
    if (strpos($file, '.svg') !== false) {
        $svg_content = file_get_contents($file);
        
        if ($svg_content !== false) {
            // Extraire les dimensions du SVG
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            
            if ($dom->loadXML($svg_content)) {
                $svg = $dom->getElementsByTagName('svg')->item(0);
                
                if ($svg) {
                    $width = $svg->getAttribute('width');
                    $height = $svg->getAttribute('height');
                    $viewBox = $svg->getAttribute('viewBox');
                    
                    // Si pas de width/height, essayer de les extraire du viewBox
                    if (empty($width) || empty($height)) {
                        if (!empty($viewBox)) {
                            $viewBoxArray = explode(' ', $viewBox);
                            if (count($viewBoxArray) === 4) {
                                $width = $viewBoxArray[2];
                                $height = $viewBoxArray[3];
                            }
                        }
                    }
                    
                    // Nettoyer les valeurs (supprimer px, em, etc.)
                    $width = (int) filter_var($width, FILTER_SANITIZE_NUMBER_INT);
                    $height = (int) filter_var($height, FILTER_SANITIZE_NUMBER_INT);
                    
                    // Valeurs par défaut si non trouvées
                    if (empty($width)) $width = 300;
                    if (empty($height)) $height = 300;
                    
                    $metadata = array(
                        'width' => $width,
                        'height' => $height,
                        'filesize' => $filesize,
                    );
                }
            }
        }
    }
    
    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'twentytwentyfour_child_svg_metadata', 10, 3);

/**
 * Afficher correctement les SVG dans l'éditeur
 */
function twentytwentyfour_child_svg_editor_display() {
    echo '<style>
        .attachment-266x266, .thumbnail img {
            width: 100% !important;
            height: auto !important;
        }
        
        .media-icon img[src$=".svg"] {
            width: 100%;
            height: auto;
        }
        
        .wp-block-image img[src$=".svg"] {
            max-width: 100%;
            height: auto;
        }
    </style>';
}
add_action('admin_head', 'twentytwentyfour_child_svg_editor_display');

/**
 * Ajouter des informations sur les SVG dans la médiathèque
 */
function twentytwentyfour_child_svg_media_info($form_fields, $post) {
    if ($post->post_mime_type === 'image/svg+xml') {
        // Obtenir la taille du fichier
        $file_path = get_attached_file($post->ID);
        $file_size = size_format(filesize($file_path));
        
        // Obtenir les dimensions
        $metadata = wp_get_attachment_metadata($post->ID);
        $width = isset($metadata['width']) ? $metadata['width'] : 'N/A';
        $height = isset($metadata['height']) ? $metadata['height'] : 'N/A';
        
        $form_fields['svg_info'] = array(
            'label' => __('Informations SVG', 'twentytwentyfour-child'),
            'input' => 'html',
            'html' => '
                <p><strong>' . __('Type:', 'twentytwentyfour-child') . '</strong> SVG (Scalable Vector Graphics)</p>
                <p><strong>' . __('Dimensions:', 'twentytwentyfour-child') . '</strong> ' . $width . ' × ' . $height . ' pixels</p>
                <p><strong>' . __('Taille:', 'twentytwentyfour-child') . '</strong> ' . $file_size . '</p>
                <p><em>' . __('Les fichiers SVG sont vectoriels et peuvent être redimensionnés sans perte de qualité.', 'twentytwentyfour-child') . '</em></p>
            '
        );
    }
    
    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'twentytwentyfour_child_svg_media_info', 10, 2);

/**
 * Ajouter un message d'information pour les SVG
 */
function twentytwentyfour_child_svg_upload_notice() {
    $screen = get_current_screen();
    
    if ($screen && ($screen->id === 'upload' || $screen->id === 'media')) {
        echo '<div class="notice notice-info is-dismissible">
            <p><strong>' . __('Fichiers SVG:', 'twentytwentyfour-child') . '</strong> ' 
            . __('Vous pouvez maintenant uploader des fichiers SVG dans la médiathèque. Pour des raisons de sécurité, certains éléments JavaScript sont automatiquement supprimés.', 'twentytwentyfour-child') . '</p>
        </div>';
    }
}
add_action('admin_notices', 'twentytwentyfour_child_svg_upload_notice');