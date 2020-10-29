<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartProductRepository;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Repository\StatusRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/cart")
 */
class CartController extends AbstractController
{
    /**
     * @Route("/", name="cart_index")
     * @param CartProductRepository $cartProductRepository
     * @return Response
     */
    public function index(CartProductRepository $cartProductRepository): Response
    {
        $cart = $this->getUser()->getCart();
        $products = $cartProductRepository->findByExampleField($cart->getId());

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'cart_products' => $products
        ]);
    }

    /**
     * @Route("/create", name="create_cart")
     * @param EntityManagerInterface $entityManager
     * @param $user
     */
    public function createCart( User $user, EntityManagerInterface $entityManager): Void
    {
        $cart = new Cart();
        $cart->setUser($user);
        $cart->setPrice(0);
        $entityManager->persist($cart);
        $entityManager->flush();
    }

    /**
     * @Route("/make/order/{cart}", name="make_order", methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param $user
     */
    public function makeOrder(Cart $cart, StatusRepository $statusRepository, EntityManagerInterface $entityManager): Response
    {
        if($cart->getPrice() !== 0){
            $status = $statusRepository->getFirst();
            $order = new Order();
            $order->setUser($cart->getUser());
            $order->setCart($cart);
            $order->setStatus($status[0]);
            $order->setPrice($cart->getPrice());
            $entityManager->persist($order);
            $entityManager->flush();
            $this->addFlash('success', 'Your order is received!');
            $this->createCart($this->getUser(), $entityManager);
        }else {
            $this->addFlash('success', 'Your cart is empty!');
        }


        return $this->redirectToRoute('cart_index');
    }

    /**
     * @Route("/add/{id}", name="add_product_to_cart", methods={"GET", "POST"})
     * @param Request $request
     * @param CartRepository $cartRepository
     * @param Product $product
     * @return Response
     */
    public function addProductToCart(Request $request, CartRepository $cartRepository, Product $product, CartProductRepository $cartProductRepository): Response
    {
        $cart = $cartRepository->findOneBy(['user' => $this->getUser()]);
        $amount = $request->get('amount');

        $cartProduct = $cartProductRepository->findOneBy([
            'cart' => $cart,
            'product' => $product
        ]);

        if($cartProduct){
            $cartProduct->setAmount($cartProduct->getAmount() + $amount);
            $cart->setPrice($cart->getPrice() + $amount*$product->getPrice());
        }else {
            $cartProduct = new CartProduct();
            $cartProduct->setCart($cart);
            $cartProduct->setProduct($product);
            $cartProduct->setAmount($amount);
            $cart->setPrice($cart->getPrice() + $product->getPrice()*$cartProduct->getAmount());
        }


        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($cartProduct);
        $entityManager->persist($cart);
        $entityManager->flush();

        $this->addFlash('success',  $product->getName() .' is now in cart!');
        return $this->redirectToRoute('product_index');
    }

    /**
     * @Route("/{id}", name="cart_product_delete", methods={"DELETE"})
     * @param Request $request
     * @param CartProduct $cartProduct
     * @param Cart $cart
     * @return Response
     */
    public function delete(Request $request, CartProduct $cartProduct, CartRepository $cartRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cartProduct->getId(), $request->request->get('_token'))) {

            //reducing price
            $cart = $cartRepository->find($cartProduct->getCart()->getId());
            $cart->setPrice($cart->getPrice() - $cartProduct->getProduct()->getPrice()*$cartProduct->getAmount());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($cartProduct);
            $entityManager->persist($cart);
            $entityManager->flush();
        }

        return $this->redirectToRoute('cart_index');
    }
}
