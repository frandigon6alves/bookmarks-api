<?php

declare(strict_types=1);

return [
    'validation.required' => 'Este campo es obligatorio.',
    'validation.blank' => 'Este campo no puede estar vacío.',
    'validation.too_long' => 'Este campo debe tener como máximo %max% caracteres.',
    'validation.type.string' => 'Este campo debe ser una cadena de texto.',
    'validation.type.string_or_null' => 'Este campo debe ser una cadena de texto o null.',
    'validation.type.list' => 'Este campo debe ser un array.',
    'validation.unknown_field' => 'Este campo no se reconoce. Campos permitidos: %fields%.',
    'validation.body.not_object' => 'El cuerpo de la petición debe ser un objeto JSON.',
    'validation.url.invalid' => 'Este campo debe ser una URL absoluta http o https.',
    'validation.tag.invalid' => 'Las etiquetas solo pueden contener letras minúsculas, dígitos y guiones simples, hasta %max% caracteres.',
    'validation.tags.too_many' => 'Un marcador puede tener como máximo %max% etiquetas.',
    'validation.limit.range' => 'El límite debe ser un número entero entre %min% y %max%.',
    'validation.cursor.invalid' => 'El cursor no es válido. Usa el valor next_cursor de una respuesta anterior.',

    'problem.validation.title' => 'La validación ha fallado',
    'problem.validation.detail' => 'El cuerpo de la petición contiene datos no válidos. Consulta "errors" para más detalles.',
    'problem.invalid_query.title' => 'Parámetros de consulta no válidos',
    'problem.invalid_query.detail' => 'Uno o más parámetros de consulta no son válidos. Consulta "errors" para más detalles.',
    'problem.malformed_json.title' => 'JSON mal formado',
    'problem.malformed_json.detail' => 'No se pudo interpretar el cuerpo de la petición como JSON.',
    'problem.bookmark_not_found.title' => 'Marcador no encontrado',
    'problem.bookmark_not_found.detail' => 'No existe ningún marcador con el id %id%.',
    'problem.duplicate_url.title' => 'URL duplicada',
    'problem.duplicate_url.detail' => 'Esta URL ya está guardada como el marcador %id%.',
    'problem.not_found.title' => 'No encontrado',
    'problem.not_found.detail' => 'No existe ningún recurso en esta ruta.',
    'problem.method_not_allowed.title' => 'Método no permitido',
    'problem.method_not_allowed.detail' => 'Este recurso solo admite: %methods%.',
    'problem.http_error.title' => 'Error en la petición',
    'problem.http_error.detail' => 'No se pudo procesar la petición.',
    'problem.internal.title' => 'Error interno del servidor',
    'problem.internal.detail' => 'Algo ha fallado por nuestra parte. Inténtalo de nuevo más tarde.',
];
