<?php

declare(strict_types=1);

return [
    'validation.required' => 'Este campo é obrigatório.',
    'validation.blank' => 'Este campo não pode ficar em branco.',
    'validation.too_long' => 'Este campo deve ter no máximo %max% caracteres.',
    'validation.type.string' => 'Este campo deve ser um texto.',
    'validation.type.string_or_null' => 'Este campo deve ser um texto ou null.',
    'validation.type.list' => 'Este campo deve ser um array.',
    'validation.unknown_field' => 'Este campo não é reconhecido. Campos permitidos: %fields%.',
    'validation.body.not_object' => 'O corpo da requisição deve ser um objeto JSON.',
    'validation.url.invalid' => 'Este campo deve ser uma URL absoluta http ou https.',
    'validation.tag.invalid' => 'As tags só podem conter letras minúsculas, dígitos e hifens simples, com até %max% caracteres.',
    'validation.tags.too_many' => 'Um favorito pode ter no máximo %max% tags.',
    'validation.limit.range' => 'O limite deve ser um número inteiro entre %min% e %max%.',
    'validation.cursor.invalid' => 'O cursor não é válido. Use o valor next_cursor de uma resposta anterior.',

    'problem.validation.title' => 'Falha na validação',
    'problem.validation.detail' => 'O corpo da requisição contém dados inválidos. Veja "errors" para mais detalhes.',
    'problem.invalid_query.title' => 'Parâmetros de consulta inválidos',
    'problem.invalid_query.detail' => 'Um ou mais parâmetros de consulta são inválidos. Veja "errors" para mais detalhes.',
    'problem.malformed_json.title' => 'JSON malformado',
    'problem.malformed_json.detail' => 'Não foi possível interpretar o corpo da requisição como JSON.',
    'problem.bookmark_not_found.title' => 'Favorito não encontrado',
    'problem.bookmark_not_found.detail' => 'Não existe favorito com o id %id%.',
    'problem.duplicate_url.title' => 'URL duplicada',
    'problem.duplicate_url.detail' => 'Esta URL já está salva como o favorito %id%.',
    'problem.not_found.title' => 'Não encontrado',
    'problem.not_found.detail' => 'Não existe nenhum recurso neste caminho.',
    'problem.method_not_allowed.title' => 'Método não permitido',
    'problem.method_not_allowed.detail' => 'Este recurso só aceita: %methods%.',
    'problem.http_error.title' => 'Erro na requisição',
    'problem.http_error.detail' => 'Não foi possível processar a requisição.',
    'problem.internal.title' => 'Erro interno do servidor',
    'problem.internal.detail' => 'Algo deu errado do nosso lado. Tente novamente mais tarde.',
];
