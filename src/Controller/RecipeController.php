<?php
namespace App\Controller;

use App\Entity\Recipe;
use OpenAI\Factory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\RecipeRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\AddRecipeType;
use Doctrine\ORM\EntityManagerInterface;
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
    public function generateRecipe(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $recipe = null;
        $error = null;
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;  // Récupération de la clé API directement depuis .env

        if (!$apiKey) {
            throw new \RuntimeException('La clé API OpenAI est manquante.');
        }

        if ($request->isMethod('POST')) {
            $ingredients = $request->request->get('ingredients', '');

            if (empty($ingredients)) {
                $error = 'Veuillez entrer des ingrédients.';
            } else {
                try {
                    // Initialisation du client OpenAI
                    $factory = new Factory();
                    $client = $factory->withApiKey($apiKey)->make();
                    $response = $client->completions()->create([
                        'model' => 'gpt-3.5-turbo',
                        'prompt' => "Donne-moi une recette avec ces ingrédients : $ingredients. 
                                     Indique le nom du plat, les ingrédients nécessaires et les étapes de préparation.",
                        'max_tokens' => 300,
                        'temperature' => 0.7,
                    ]);

                    $generatedRecipe = $response->choices[0]->text ?? 'Erreur lors de la génération.';

                    // Si l'utilisateur est connecté, on sauvegarde la recette en base de données
                    $user = $security->getUser();
                    if ($user) {
                        $recipeEntity = new Recipe();
                        $recipeEntity->setTitle('Recette IA : ' . substr($generatedRecipe, 0, 30) . '...');
                        $recipeEntity->setContent($generatedRecipe);
                        $recipeEntity->setAuthor($user);

                        $entityManager->persist($recipeEntity);
                        $entityManager->flush();
                    }

                    $recipe = $generatedRecipe;
                } catch (\Exception $e) {
                    dump($e->getMessage()); die; // Affiche l'erreur exacte

                    $error = 'Une erreur est survenue lors de la génération de la recette.';
                }
            }
        }

        return $this->render('recipes/generate.html.twig', [
            'recipe' => $recipe,
            'error' => $error,
        ]);
    }
}
