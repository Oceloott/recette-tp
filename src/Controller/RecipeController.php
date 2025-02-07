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
    #[Route('/recipe/{id}', name: 'recipe_show', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function show(Recipe $recipe, Request $request): Response
    {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        $nutritionAnalysis = null;
        $error = null;

        // Calcul de la note moyenne
        $reviews = $recipe->getReviews();
        $averageRating = null;
        if (count($reviews) > 0) {
            $total = array_reduce($reviews->toArray(), fn($carry, $review) => $carry + $review->getRating(), 0);
            $averageRating = $total / count($reviews);
        }

        if ($request->isMethod('POST')) {
            if (!$apiKey) {
                throw new \RuntimeException('La clé API OpenAI est manquante.');
            }

            $ingredientsText = [];
            foreach ($recipe->getIngredients() as $ingredient) {
                $ingredientsText[] = "{$ingredient->getQuantity()} {$ingredient->getUnit()} de {$ingredient->getName()}";
            }
            $ingredientsList = implode(', ', $ingredientsText);

            try {
                $factory = new \OpenAI\Factory();
                $client = $factory->withApiKey($apiKey)->make();
                $response = $client->chat()->create([
                    'model' => 'gpt-4-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => "Tu es un expert en nutrition et en diététique. 
                        Lorsqu'on te donne une liste d'ingrédients d'une recette, tu dois analyser son apport nutritionnel.
                        Fournis une analyse détaillée en JSON avec :
                        - **calories** par portion
                        - **protéines** (g)
                        - **lipides** (g)
                        - **glucides** (g)
                        - **fibres** (g)
                        - **conseils nutritionnels** (par ex. : équilibré, trop gras, riche en protéines).
                        
                        Réponds uniquement en JSON strictement formaté, sans texte explicatif autour."
                        ],
                        ['role' => 'user', 'content' => "Analyse les valeurs nutritionnelles de cette recette contenant : $ingredientsList.
                        Retourne uniquement un JSON formaté comme ceci :
                        ```json
                        {
                            \"calories\": 500,
                            \"proteines\": 30,
                            \"lipides\": 10,
                            \"glucides\": 60,
                            \"fibres\": 5,
                            \"conseil\": \"Cette recette est riche en glucides et idéale pour un repas énergétique.\"
                        }
                        ```"
                        ],
                    ],
                    'max_tokens' => 300,
                    'temperature' => 0.5,
                ]);

                $jsonResponse = $response->choices[0]->message->content;
                $jsonResponse = preg_replace('/```json|```/', '', $jsonResponse);
                $jsonResponse = trim($jsonResponse);

                $nutritionAnalysis = json_decode($jsonResponse, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Erreur JSON : ' . json_last_error_msg());
                }
            } catch (\Exception $e) {
                $error = 'Erreur lors de l’analyse nutritionnelle : ' . $e->getMessage();
            }
        }

        return $this->render('recipes/recipe.html.twig', [
            'recipe' => $recipe,
            'averageRating' => $averageRating,
            'nutritionAnalysis' => $nutritionAnalysis,
            'error' => $error,
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
        Request                $request,
        EntityManagerInterface $entityManager,
        Security               $security
    ): Response
    {
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
                            [
                                'role' => 'system',
                                'content' => "Tu es un chef cuisinier passionné qui aide les amateurs à découvrir la cuisine. 
                                    Tes recettes sont simples, savoureuses et accessibles à tous. 
                                    
                                    Génére uniquement un JSON strictement formaté avec :
                                    
                                    - **Nom de la recette** basé sur les ingrédients fournis.
                                    - **Brève description** du plat.
                                    - **Temps de préparation et cuisson** en minutes.
                                    - **Liste des ingrédients** (uniquement ceux fournis) avec :
                                      - `name`
                                      - `quantity` (entier)
                                      - `unit` (grammes, ml, pièce, etc.)
                                    - **Étapes numérotées** de préparation avec :
                                      - `stepOrder` (entier)
                                      - `description`
                                    - **Conseils et variantes**.
                                    
                                    Ne génère que du JSON sans texte explicatif autour."
                            ],
                            [
                                'role' => 'user',
                                'content' => "Voici les ingrédients disponibles : **$ingredients**. 
                                    
                                    Génére une recette en JSON formaté :
                                    
                                    1. `title` : Nom de la recette.
                                    2. `description` : Brève description.
                                    3. `prepTime` et `cookTime` en minutes.
                                    4. `ingredients` : Liste avec `name`, `quantity`, `unit`.
                                    5. `steps` : Liste numérotée avec `stepOrder` et `description`.
                                    6. `tips` : Conseils et variantes possibles.
                                    
                                    Exemple attendu :
                                    
                                    ```json
                                    {
                                      \"title\": \"Crêpes moelleuses\",
                                      \"description\": \"Des crêpes légères et faciles à faire.\",
                                      \"prepTime\": 10,
                                      \"cookTime\": 20,
                                      \"ingredients\": [
                                        {\"name\": \"Farine\", \"quantity\": 250, \"unit\": \"g\"},
                                        {\"name\": \"Oeufs\", \"quantity\": 3, \"unit\": \"pièce\"},
                                        {\"name\": \"Lait\", \"quantity\": 500, \"unit\": \"ml\"}
                                      ],
                                      \"steps\": [
                                        {\"stepOrder\": 1, \"description\": \"Mélanger la farine et les œufs.\"},
                                        {\"stepOrder\": 2, \"description\": \"Ajouter le lait progressivement en remuant.\"}
                                      ]
                                    }
                                    ```
                                    
                                    Réponds uniquement en JSON strictement formaté sans texte supplémentaire."
                            ]
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
