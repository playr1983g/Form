<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\ChoiceList\EntityChoiceList;
use Symfony\Component\Form\DataProcessor\CollectionMerger;
use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;
use Symfony\Component\Form\ValueTransformer\EntitiesToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\EntityToIdTransformer;
use Symfony\Component\Form\ValueTransformer\ArrayToChoicesTransformer;
use Symfony\Component\Form\ValueTransformer\ScalarToChoicesTransformer;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\InvalidOptionsException;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\NoResultException;

/**
 * A field for selecting one or more from a list of Doctrine 2 entities
 *
 * You at least have to pass the entity manager and the entity class in the
 * options "em" and "class".
 *
 * <code>
 * $form->add(new EntityChoiceField('tags', array(
 *     'em' => $em,
 *     'class' => 'Application\Entity\Tag',
 * )));
 * </code>
 *
 * Additionally to the options in ChoiceField, the following options are
 * available:
 *
 *  * em:             The entity manager. Required.
 *  * class:          The class of the selectable entities. Required.
 *  * property:       The property displayed as value of the choices. If this
 *                    option is not available, the field will try to convert
 *                    objects into strings using __toString().
 *  * query_builder:  The query builder for fetching the selectable entities.
 *                    You can also pass a closure that receives the repository
 *                    as single argument and returns a query builder.
 *
 * The following sample outlines the use of the "query_builder" option
 * with closures.
 *
 * <code>
 * $form->add(new EntityChoiceField('tags', array(
 *     'em' => $em,
 *     'class' => 'Application\Entity\Tag',
 *     'query_builder' => function ($repository) {
 *         return $repository->createQueryBuilder('t')->where('t.enabled = 1');
 *     },
 * )));
 * </code>
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class EntityChoiceField extends ChoiceField
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addRequiredOption('em');
        $this->addRequiredOption('class');
        $this->addOption('property');
        $this->addOption('query_builder');

        // Override option - it is not required for this subclass
        $this->addOption('choices', array());

        parent::configure();

        $this->choiceList = new EntityChoiceList(
            $this->getOption('em'),
            $this->getOption('class'),
            $this->getOption('property'),
            $this->getOption('query_builder'),
            $this->getOption('choices'),
            $this->getOption('preferred_choices')
        );

        $transformers = array();

        if ($this->getOption('multiple')) {
            $this->setDataProcessor(new CollectionMerger($this));

            $transformers[] = new EntitiesToArrayTransformer($this->choiceList);

            if ($this->getOption('expanded')) {
                $transformers[] = new ArrayToChoicesTransformer($this->choiceList);
            }
        } else {
            $transformers[] = new EntityToIdTransformer($this->choiceList);

            if ($this->getOption('expanded')) {
                $transformers[] = new ScalarToChoicesTransformer($this->choiceList);
            }
        }

        if (count($transformers) > 1) {
            $this->setValueTransformer(new ValueTransformerChain($transformers));
        } else {
            $this->setValueTransformer(current($transformers));
        }
    }
}