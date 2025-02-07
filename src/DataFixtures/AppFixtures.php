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

        $images = [
            'recipe1.webp', 'recipe2.webp', 'recipe3.webp',
            'recipe4.webp', 'recipe5.webp', 'recipe6.webp',
        ];

        $ingredientNames = [
            'Farine', 'Sucre', 'Beurre', 'Œufs', 'Lait', 'Sel', 'Poivre',
            'Huile d\'olive', 'Huile de tournesol', 'Huile de coco', 'Levure chimique',
            'Levure boulangère', 'Bicarbonate de soude', 'Miel', 'Sirop d\'érable',
            'Vinaigre balsamique', 'Vinaigre de cidre', 'Fécule de maïs', 'Chapelure',
            'Lait d\'amande', 'Lait de coco', 'Lait de soja', 'Crème fraîche', 'Yaourt nature',
            'Poulet', 'Dinde', 'Bœuf', 'Poisson', 'Saumon', 'Thon', 'Pommes de terre', 'Carottes',
            'Tomates', 'Courgettes', 'Champignons', 'Poivrons', 'Oignons', 'Ail', 'Pâtes', 'Riz',
            'Haricots verts', 'Lentilles', 'Pois chiches', 'Épinards', 'Fromage', 'Jambon'
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

        $adminUser = new User();
        $adminUser->setEmail('codeingniter@gmail.com')
            ->setFirstname('codeingniter')
            ->setLastname('codeingniter')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword($this->passwordHasher->hashPassword($adminUser, 'password'))
            ->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($adminUser);
        $users[] = $adminUser;

        $recipes = [];
        foreach ($recipeTitles as $title) {
            $recipe = new Recipe();
            $recipe->setTitle($title)
                ->setDescription($faker->paragraph)
                ->setPrepTime($faker->numberBetween(5, 60))
                ->setCookTime($faker->numberBetween(5, 60))
                ->setAuthor($faker->randomElement($users))
                ->setImage($faker->randomElement($images));

            $usedIngredientNames = [];
            $numIngredients = mt_rand(3, 6);

            for ($i = 0; $i < $numIngredients; $i++) {
                do {
                    $ingredientName = $faker->randomElement($ingredientNames);
                } while (in_array($ingredientName, $usedIngredientNames));

                $usedIngredientNames[] = $ingredientName;

                $ingredient = new Ingredient();
                $ingredient->setName($ingredientName)
                    ->setQuantity($faker->numberBetween(50, 500))
                    ->setUnit($faker->randomElement(['g', 'ml', 'pièce']))
                    ->setRecipe($recipe);

                $manager->persist($ingredient);
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
