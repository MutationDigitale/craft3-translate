<?php

namespace mutation\translate\interfaces;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * The GraphQL type of the static messages
 */
class StaticMessageInterface
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'StaticMessagesType';
    }

    /**
     * @return Type
     */
    public static function getType()
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn() => new ObjectType([
            'name' => self::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by static messages.',
        ]));
    }

    /**
     * @return array
     */
    public static function getFieldDefinitions(): array
    {
        return [
            'key' => Type::string(),
            'message' => Type::string(),
            'language' => Type::string(),
            'category' => Type::string(),
            'dateCreated' => Type::string(),
        ];
    }
}
