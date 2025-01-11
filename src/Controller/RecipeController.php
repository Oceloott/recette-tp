<?php

namespace App\Controller;

use App\Entity\Recipe;
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
            $this->addFlash('error', 'You must be logged in to add a recipe.');
            return $this->redirectToRoute('app_login');
        }

        $recipe->setAuthor($user);

        $form = $this->createForm(AddRecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($recipe);
            $entityManager->flush();

            $this->addFlash('success', 'Recipe successfully added!');
            return $this->redirectToRoute('recipes_list');
        }

        return $this->render('recipes/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
