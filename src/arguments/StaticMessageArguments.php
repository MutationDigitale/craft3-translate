<?php

namespace mutation\translate\arguments;

use GraphQL\Type\Definition\Type;

class StaticMessageArguments
{
    public static function getArguments(): array
    {
        return [
            'language' => [
                'name' => 'language',
                'type' => Type::listOf(Type::string()),
                'description' => 'Determines which language(s) the static messages should be queried in. Defaults to the current (requested) language.'
            ],
            'category' => [
                'name' => 'category',
                'type' => Type::listOf(Type::string()),
                'description' => 'Determines which category(ies) the static messages should be queried in. Defaults to the site category.'
            ],
        ];
    }
}