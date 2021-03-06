<?php

namespace Com\PaulDevelop\Library\Project;

use Com\PaulDevelop\Library\Modeling\Entities\AttributeCollection;
use Com\PaulDevelop\Library\Modeling\Entities\EntityCollection;
use Com\PaulDevelop\Library\Modeling\Entities\PropertyCollection;

class Parser
{
    public static function parse($projectFileName = '')
    {
        $projectAttributeCollection = new AttributeCollection();
        $project = simplexml_load_file($projectFileName);

        // project attributes
        $projectNamespaces = $project->getNamespaces(true);
        foreach ($projectNamespaces as $namespaceName => $namespaceUri) {
            $projectAttributes = $project->attributes($namespaceName, true);
            foreach ($projectAttributes as $key => $value) {
                $projectAttributeCollection->add(
                    new Attribute($namespaceName, $key, (string)$value),
                    $namespaceName.':'.$key
                );
            }
        }

        // model attributes
        $modelAttributeCollection = new AttributeCollection();
        $model = $project->{'model'};
        /** @var \SimpleXMLElement $model */
        $modelNamespaces = $model->getNamespaces(true);
        foreach ($modelNamespaces as $namespaceName => $namespaceUri) {
            $modelAttributes = $model->attributes($namespaceName, true);
            foreach ($modelAttributes as $key => $value) {
                $modelAttributeCollection->add(
                    new Attribute($namespaceName, $key, (string)$value),
                    $namespaceName.':'.$key
                );
            }
        }

        // model entities
        $modelEntityCollection = new EntityCollection();
        foreach ($project->{'model'}->{'entity'} as $entity) {
            $entityAttributeCollection = new AttributeCollection();
            /** @var \SimpleXMLElement $entity */
            $entityNamespaces = $entity->getNamespaces(true);
            foreach ($entityNamespaces as $namespaceName => $namespaceUri) {
                $entityAttributes = $entity->attributes($namespaceName, true);
                foreach ($entityAttributes as $key => $value) {
                    $entityAttributeCollection->add(
                        new Attribute($namespaceName, $key, (string)$value),
                        $namespaceName.':'.$key
                    );
                }
            }

            $entityPropertyCollection = new PropertyCollection();
            foreach ($entity->{'property'} as $property) {
                $propertyAttributeCollection = new AttributeCollection();
                /** @var \SimpleXMLElement $property */
                $propertyNamespaces = $property->getNamespaces(true);
                foreach ($propertyNamespaces as $namespaceName => $namespaceUri) {
                    $propertyAttributes = $property->attributes($namespaceName, true);
                    foreach ($propertyAttributes as $key => $value) {
                        $propertyAttributeCollection->add(
                            new Attribute($namespaceName, $key, (string)$value),
                            $namespaceName.':'.$key
                        );
                    }
                }

                $entityPropertyCollection->add(
                    new Property(
                        $propertyAttributeCollection['property:name']->Value,
                        $propertyAttributeCollection
                    ),
                    $propertyAttributeCollection['property:name']->Value
                );
            }

            $modelEntityCollection->add(
                new Entity(
                    $entityAttributeCollection['entity:namespace']->Value,
                    $entityAttributeCollection['entity:name']->Value,
                    $entityAttributeCollection,
                    $entityPropertyCollection
                ),
                $entityAttributeCollection['entity:namespace']->Value
                .'.'
                .$entityAttributeCollection['entity:name']->Value
            );
        }

        $project = new Project(
            new Model(self::processEntityInheritation($modelEntityCollection), $modelAttributeCollection),
            $projectAttributeCollection
        );

        return $project;
    }

    /**
     * @param $entities
     *
     * @return EntityCollection
     * @throws \Com\PaulDevelop\Library\Common\ArgumentException
     * @throws \Com\PaulDevelop\Library\Common\TypeCheckException
     */
    private static function processEntityInheritation($entities)
    {
        // init
        $result = new EntityCollection();

        // action
        $entitiesUsed = array(); // don't need to be in final entity list / to be generated

        // iterate only entities with attribute "entity:extends"
        foreach ($entities as $entity) {
            /** @var \Com\PaulDevelop\Library\Modeling\Entities\IEntity $entity */
            //echo $entity->Name.PHP_EOL;

            // check, if attribute "entity:extends" exists
            if ($entity->Attributes['entity:extends'] != null) {
                //echo '  extends: '.$entity->Attributes['entity:extends']->Value.PHP_EOL;

                // create new entity
                $newEntity = new Entity();

                // get inherited entity
                $inheritedEntity = $entities[$entity->Attributes['entity:extends']->Value];
                /** @var \Com\PaulDevelop\Library\Modeling\Entities\IEntity $inheritedEntity */

                // mark current and inherited entity as used
                if (!array_key_exists($inheritedEntity->Namespace.'.'.$inheritedEntity->Name, $entitiesUsed)) {
                    $entitiesUsed[$inheritedEntity->Namespace.'.'.$inheritedEntity->Name] = 1;
                }
                if (!array_key_exists($entity->Namespace.'.'.$entity->Name, $entitiesUsed)) {
                    $entitiesUsed[$entity->Namespace.'.'.$entity->Name] = 1;
                }

                // copy inherited properties
                foreach ($inheritedEntity->Properties as $inheritedProperty) {
                    /** @var \Com\PaulDevelop\Library\Modeling\Entities\IProperty $inheritedProperty */
                    $newEntity->Properties->add($inheritedProperty, $inheritedProperty->Name);
                }

                // copy entity properties
                foreach ($entity->Properties as $property) {
                    /** @var \Com\PaulDevelop\Library\Modeling\Entities\IProperty $property */
                    if ($newEntity->Properties[$property->Name] != null) {
                        $newEntity->Properties[$property->Name] = $property;
                    } else {
                        $newEntity->Properties->add($property, $property->Name);
                        //echo '  add property '.$property->Name.PHP_EOL;
                    }
                }

                // copy inherited attributes
                foreach ($inheritedEntity->Attributes as $inheritedAttribute) {
                    /** @var \Com\PaulDevelop\Library\Modeling\Entities\IAttribute $inheritedAttribute */
                    $newEntity->Attributes->add(
                        $inheritedAttribute,
                        $inheritedAttribute->Namespace.':'.$inheritedAttribute->Key
                    );
                }

                // overwrite them with entity attributes
                foreach ($entity->Attributes as $attribute) {
                    /** @var \Com\PaulDevelop\Library\Modeling\Entities\IAttribute $attribute */
                    if ($newEntity->Attributes[$attribute->Namespace.':'.$attribute->Key] != null) {
                        $newEntity->Attributes[$attribute->Namespace.':'.$attribute->Key] = $attribute;
                    } else {
                        $newEntity->Attributes->add(
                            $attribute,
                            $attribute->Namespace.':'.$attribute->Key
                        );
                    }
                }

                // set namespace and name
                $newEntity->Namespace = $entity->Namespace;
                $newEntity->Name = $entity->Name;

                $result->add($newEntity, $newEntity->Namespace.':'.$newEntity->Name);
            }
        }

        // iterate one more time, this time skip used entities
        foreach ($entities as $entity) {
            /** @var \Com\PaulDevelop\Library\Modeling\Entities\IEntity $entity */

            // check, if not used
            if (!array_key_exists($entity->Namespace.'.'.$entity->Name, $entitiesUsed)) {
                $result->add($entity, $entity->Namespace.':'.$entity->Name);
            }
        }

        // return
        return $result;
    }
}
