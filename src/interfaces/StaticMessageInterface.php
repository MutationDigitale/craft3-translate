<?php

namespace mutation\translate\interfaces;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Class LinkGqlType
 */
class StaticMessageInterface
{
    /**
     * @return string
     */
    static public function getName(): string
    {
        return 'StaticMessagesType';
    }

    /**
     * @return Type
     */
    static public function getType()
    {
        if ($type = GqlEntityRegistry::getEntity(self::class)) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(
            self::class,
            new ObjectType(
                [
                    'name' => static::getName(),
                    'fields' => self::class . '::getFieldDefinitions',
                    'description' => 'This is the interface implemented by static messages.',
                ]
            )
        );
    }

    /**
     * @rejturn array
     */
    public static function getFieldDefinitions(): array
    {
        return [
            'key' => Type::string(),
            'message' => Type::string(),
            'language' => Type::string(),
            'category' => Type::string(),
        ];
    }
}
