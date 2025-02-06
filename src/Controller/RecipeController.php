<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\Ingredient;
use App\Entity\Step;
use App\Form\AddRecipeType;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;

class RecipeController extends AbstractController
{
    #[Route('/recipe/{id}', name: 'recipe_show')]
    #[IsGranted('ROLE_USER')]
    public function show(Recipe $recipe): Response
    {
        $reviews = $recipe->getReviews();
        $averageRating = null;

        if (count($reviews) > 0) {
            $total = array_reduce($reviews->toArray(), fn($carry, $review) => $carry + $review->getRating(), 0);
            $averageRating = $total / count($reviews);
        }

        return $this->render('recipes/recipe.html.twig', [
            'recipe' => $recipe,
            'averageRating' => $averageRating,
        ]);
    }

    #[Route('/recipes', name: 'recipes_list')]
    #[IsGranted('ROLE_USER')]
    public function index(RecipeRepository $recipeRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');

        if ($search) {
            $recipes = $recipeRepository->createQueryBuilder('r')
                ->where('r.title LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->getQuery()
                ->getResult();
        } else {
            $recipes = $recipeRepository->findAll();
        }

        return $this->render('recipes/recipes_list.html.twig', [
            'recipes' => $recipes,
        ]);
    }

    #[Route('/add/recipe', name: 'add_recipe')]
    #[IsGranted('ROLE_USER')]
    public function addRecipe(
        Request $request,
        EntityManagerInterface $entityManager,
        Security $security
    ): Response {
        $recipe = new Recipe();
        $user = $security->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour ajouter une recette.');
            return $this->redirectToRoute('app_login');
        }

        $recipe->setAuthor($user);
        $form = $this->createForm(AddRecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($recipe);
            $entityManager->flush();

            $this->addFlash('success', 'Recette ajoutée avec succès !');
            return $this->redirectToRoute('recipes_list');
        }

        return $this->render('recipes/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/generate-recipe', name: 'generate_recipe', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function generateRecipe(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $recipe = null;
        $error = null;
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;

        if (!$apiKey) {
            throw new \RuntimeException('La clé API OpenAI est manquante.');
        }

        if ($request->isMethod('POST')) {
            $ingredients = $request->request->get('ingredients', '');

            if (empty($ingredients)) {
                $error = 'Veuillez entrer des ingrédients.';
            } else {
                try {
                    $factory = new \OpenAI\Factory();
                    $client = $factory->withApiKey($apiKey)->make();
                    $response = $client->chat()->create([
                        'model' => 'gpt-3.5-turbo',
                        'messages' => [
                            ['role' => 'system', 'content' => "Tu es un chef cuisinier passionné qui aide les amateurs à découvrir la cuisine. Tes recettes sont simples, savoureuses et accessibles à tous. Génére uniquement un JSON strictement formaté avec :\n\n- **Nom de la recette** basé sur les ingrédients fournis.\n- **Brève description** du plat.\n- **Temps de préparation et cuisson** en minutes.\n- **Liste des ingrédients** (uniquement ceux fournis) avec :\n  - `name`\n  - `quantity` (entier)\n  - `unit` (grammes, ml, pièce, etc.)\n- **Étapes numérotées** de préparation avec :\n  - `stepOrder` (entier)\n  - `description`\n- **Conseils et variantes**.\n\nNe génère que du JSON sans texte explicatif autour."],
                            ['role' => 'user', 'content' => "Voici les ingrédients disponibles : **$ingredients**. Génére une recette en JSON formaté :\n\n1. `title` : Nom de la recette.\n2. `description` : Brève description.\n3. `prepTime` et `cookTime` en minutes.\n4. `ingredients` : Liste avec `name`, `quantity`, `unit`.\n5. `steps` : Liste numérotée avec `stepOrder` et `description`.\n6. `tips` : Conseils et variantes possibles.\n\nExemple attendu :\n```json\n{\n  \"title\": \"Crêpes moelleuses\",\n  \"description\": \"Des crêpes légères et faciles à faire.\",\n  \"prepTime\": 10,\n  \"cookTime\": 20,\n  \"ingredients\": [\n    {\"name\": \"Farine\", \"quantity\": 250, \"unit\": \"g\"},\n    {\"name\": \"Oeufs\", \"quantity\": 3, \"unit\": \"pièce\"},\n    {\"name\": \"Lait\", \"quantity\": 500, \"unit\": \"ml\"}\n  ],\n  \"steps\": [\n    {\"stepOrder\": 1, \"description\": \"Mélanger la farine et les œufs.\"},\n    {\"stepOrder\": 2, \"description\": \"Ajouter le lait progressivement en remuant.\"}\n  ]\n}\n```\n\nRéponds uniquement en JSON strictement formaté sans texte supplémentaire."]
                        ],
                        'max_tokens' => 500,
                        'temperature' => 0.5,
                    ]);


                    // Récupération des données et formatage si probleme du json
                    $jsonResponse = $response->choices[0]->message->content;

                    $jsonResponse = preg_replace('/```json|```/', '', $jsonResponse);
                    $jsonResponse = trim($jsonResponse);

                    $recipeData = json_decode($jsonResponse, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Erreur JSON : ' . json_last_error_msg());
                    }
                    if ($recipeData) {
                        $user = $security->getUser();
                        if ($user) {
                            $recipe = new Recipe();
                            $recipe->setTitle($recipeData['title']);
                            $recipe->setDescription($recipeData['description']);
                            $recipe->setPrepTime($recipeData['prepTime']);
                            $recipe->setCookTime($recipeData['cookTime']);
                            $recipe->setAuthor($user);

                            $entityManager->persist($recipe);

                            foreach ($recipeData['ingredients'] as $ingredientData) {
                                $ingredient = new Ingredient();
                                $ingredient->setName($ingredientData['name']);
                                $ingredient->setQuantity($ingredientData['quantity']);
                                $ingredient->setUnit($ingredientData['unit']);
                                $ingredient->setRecipe($recipe);
                                $entityManager->persist($ingredient);
                            }

                            foreach ($recipeData['steps'] as $stepData) {
                                $step = new Step();
                                $step->setStepOrder($stepData['stepOrder']);
                                $step->setDescription($stepData['description']);
                                $step->setRecipe($recipe);
                                $entityManager->persist($step);
                            }

                            $entityManager->flush();
                            $this->addFlash('success', 'Recette générée et sauvegardée avec succès !');
                        }
                    } else {
                        $error = 'Erreur lors de la génération de la recette.';
                    }
                } catch (\Exception $e) {
                    $error = 'Une erreur est survenue lors de la génération de la recette : ' . $e->getMessage();
                }
            }
        }

        return $this->render('recipes/generate.html.twig', [
            'recipe' => $recipe,
            'error' => $error,
        ]);
    }
}
