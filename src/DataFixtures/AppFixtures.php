<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Recipe;
use App\Entity\Ingredient;
use App\Entity\Step;
use App\Entity\Review;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $ingredientNames = [
            'Farine', 'Sucre', 'Beurre', 'Œufs', 'Lait', 
            'Sel', 'Poivre', 'Huile d\'olive', 'Tomates', 'Oignons',
            'Ail', 'Pâtes', 'Poulet', 'Crème fraîche', 'Fromage'
        ];

        $recipeTitles = [
            'Tarte aux pommes', 'Spaghetti bolognaise', 'Poulet rôti',
            'Quiche lorraine', 'Curry de légumes', 'Gâteau au chocolat',
            'Soupe à l\'oignon', 'Ratatouille', 'Lasagnes', 'Tarte Tatin'
        ];

        $stepDescriptions = [
            'Préchauffer le four.', 'Couper les légumes en petits morceaux.',
            'Faire chauffer une poêle avec de l\'huile.', 'Ajouter les épices.',
            'Mélanger les ingrédients.', 'Faire mijoter à feu doux.',
            'Servir chaud avec un accompagnement.', 'Laisser reposer au frais.',
            'Dorer au four pendant 30 minutes.', 'Battre les œufs en omelette.'
        ];

        $reviewComments = [
            'Délicieux et facile à préparer !', 'Un peu trop salé à mon goût.',
            'Excellente recette, mes invités ont adoré.', 
            'Temps de cuisson un peu long.', 'Simple mais efficace !',
            'Manque un peu de saveur.', 'Une nouvelle recette à tester absolument.'
        ];

        $users = [];
        $roles = ['ROLE_ADMIN', 'ROLE_USER', 'ROLE_BANNED'];
        foreach ($roles as $role) {
            $user = new User();
            $user->setEmail($faker->email)
                ->setFirstname($faker->firstName)
                ->setLastname($faker->lastName)
                ->setRoles([$role])
                ->setPassword($this->passwordHasher->hashPassword($user, 'password'))
                ->setCreatedAt(new \DateTimeImmutable());
            $manager->persist($user);
            $users[] = $user;
        }

        $ingredients = [];
        foreach ($ingredientNames as $name) {
            $ingredient = new Ingredient();
            $ingredient->setName($name)
                ->setQuantity($faker->randomFloat(2, 1, 100))
                ->setUnit($faker->randomElement(['g', 'ml', 'pieces']));
            $manager->persist($ingredient);
            $ingredients[] = $ingredient;
        }

        $recipes = [];
        foreach ($recipeTitles as $title) {
            $recipe = new Recipe();
            $recipe->setTitle($title)
                ->setDescription($faker->paragraph)
                ->setPrepTime($faker->numberBetween(5, 60))
                ->setCookTime($faker->numberBetween(5, 60))
                ->setAuthor($faker->randomElement($users));
            foreach ($faker->randomElements($ingredients, mt_rand(3, 6)) as $ingredient) {
                $recipe->addIngredient($ingredient);
            }
            $manager->persist($recipe);
            $recipes[] = $recipe;
        }

        foreach ($recipes as $recipe) {
            $numSteps = mt_rand(3, 5);
            for ($i = 1; $i <= $numSteps; $i++) {
                $step = new Step();
                $step->setStepOrder($i)
                    ->setDescription($faker->randomElement($stepDescriptions))
                    ->setRecipe($recipe);
                $manager->persist($step);
            }
        }

        foreach ($recipes as $recipe) {
            $numReviews = mt_rand(1, 3);
            for ($i = 0; $i < $numReviews; $i++) {
                $review = new Review();
                $review->setComment($faker->randomElement($reviewComments))
                    ->setRating($faker->numberBetween(1, 5))
                    ->setRecipe($recipe)
                    ->setUser($faker->randomElement($users))
                    ->setCreatedAt(new \DateTimeImmutable());
                    $manager->persist($review);
            }
        }

        $manager->flush();
    }
}

    
