<?php

namespace App\Controller\Crud;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\CartRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\UploaderHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @Route("/crud/product")
 */
class ProductController extends AbstractController
{
    /**
     * @Route("/", name="product_index", methods={"GET"})
     */
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository, CartRepository $cartRepository, SessionInterface $session): Response
    {
        $session->start();
        return $this->render('crud/product/index.html.twig', [
            'products' => $productRepository->findAll(),
            'categories' => $categoryRepository->findAll(),
            'cart' => $cartRepository->findOneBy(['session' => $session->getId()])
        ]);
    }

    /**
     * @IsGranted("ROLE_ADMIN", statusCode=404, message="You don't have premmision for this!")
     * @Route("/new", name="product_new", methods={"GET","POST"})
     */
    public function new(Request $request, UploaderHelper $uploaderHelper): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if(!$form->get('price')->getData()) {
                $this->addFlash('warning', 'You didn\'t input the price, you should edit ' . $product->getName() );
                $product->setPrice(0);
            }

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form->get('imageFile')->getData();

            if ($uploadedFile) {
                $newFilename = $uploaderHelper->uploadProductImage($uploadedFile);
                $product->setImage($newFilename);
            } else {
                $product->setImage('default.jpg');
            }


            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute('product_index');
        }

        return $this->render('crud/product/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @IsGranted("ROLE_ADMIN", statusCode=404, message="You don't have premmision for this!")
     * @Route("/{id}/edit", name="product_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Product $product, UploaderHelper $uploaderHelper): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form['imageFile']->getData();

            if($uploadedFile) {
                $newFilename = $uploaderHelper->uploadProductImage($uploadedFile);
                $product->setImage($newFilename);
            }

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('product_index');
        }

        return $this->render('crud/product/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @IsGranted("ROLE_ADMIN", statusCode=404, message="You don't have premmision for this!")
     * @Route("/{id}", name="product_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Product $product): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('product_index');
    }
}


