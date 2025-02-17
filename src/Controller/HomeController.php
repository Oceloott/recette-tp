<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\RecipeRepository;

class HomeController extends AbstractController
{
        #[Route('/', name: 'app_home')]
        public function index(RecipeRepository $recipeRepository): Response
        {
            $recipes = $recipeRepository->getThreeRandomRecipes();

            return $this->render('home/index.html.twig', [
                'recipes' => $recipes,
            ]);
        }
}
