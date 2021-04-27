<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $courses = ['tech-dev-web' => 'Технологии разработки WEB-приложений',
            'php-language' => 'Язык программирования PHP', 'database-course' => 'Базы данных',
            'vuejs-course' => 'Vue.js', 'symfony-course' => 'Symfony'];

        foreach ($courses as $code => $name) {
            $course = new Course();
            $course->setName($name);
            $course->setDescription('Перед Вами новейший курс "' . $name . '"');
            $course->setCode($code);
            $manager->persist($course);

            for ($i = 1; $i < 6; ++$i) {
                $lesson = new Lesson();
                $lesson->setName('Урок №' . $i . ' по курсу ' . $name);
                $lesson->setContent('Это содержмиое урока №' . $i . ' по курсу "' . $name . '"');
                $lesson->setNumber($i);
                $lesson->setCourse($course);
                $manager->persist($lesson);
            }
        }
        $manager->flush();
    }
}
