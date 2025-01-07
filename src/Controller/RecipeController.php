<?php

namespace App\Controller;

use App\Entity\Recipe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\RecipeRepository;
use Symfony\Component\HttpFoundation\Request;

class RecipeController extends AbstractController
{
    #[Route('/recipe/{id}', name: 'recipe_show')]
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
}
