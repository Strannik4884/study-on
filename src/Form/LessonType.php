<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

class LessonType extends AbstractType
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Название не может быть пустым'
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Длина названия не должна первышать {{ limit }} символов'
                    ])
                ]
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Содержимое',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Содержимое курса не может быть пустым'
                    ])
                ]
            ])
            ->add('number', NumberType::class, [
                'label' => 'Номер',
                'constraints' => [
                    new Range([
                        'min' => 1,
                        'minMessage' => 'Номер урока не может быть меньше {{ min }}',
                        'max' => 10000,
                        'maxMessage' => 'Номер урока не может быть больше {{ max }}'
                    ])
                ]
            ])
            ->add('course', HiddenType::class)
        ;

        $builder->get('course')
            ->addModelTransformer(new CallbackTransformer(
                function (Course $course) {
                    return $course->getId();
                },
                function (int $courseId) {
                    return $this->entityManager->getRepository(Course::class)->find($courseId);
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
        ]);
    }
}
