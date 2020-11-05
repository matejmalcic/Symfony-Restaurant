<?php

declare(strict_types=1);

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
use Dompdf\Dompdf;
use Dompdf\Options;
use phpDocumentor\Reflection\Types\Void_;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
    public function index(CartProductRepository $cartProductRepository, CartRepository $cartRepository, SessionInterface $session): Response
    {
        $session->start();
        $cart = $cartRepository->findOneBy(['session' => $session->getId()]);
        $products = $cartProductRepository->findByExampleField($cart->getId());

        $session->set('cart', $cart);

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
    public function createCart(EntityManagerInterface $entityManager, SessionInterface $session): Void
    {
        $cart = new Cart();
        $cart->setPrice(0);
        $cart->setSession($session->getId());
        $entityManager->persist($cart);
        $entityManager->flush();
    }

    /**
     * @Route("/make/order/{cart}", name="make_order", methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param $user
     */
    public function makeOrder(Cart $cart, StatusRepository $statusRepository, EntityManagerInterface $entityManager, CartProductRepository $cartProductRepository, SessionInterface $session): Response
    {
        if($cart->getPrice() != 0){
            $status = $statusRepository->findOneBy([],['number' => 'ASC']);
            $order = new Order();
            $order->setUser($this->getUser());
            $order->setCart($cart);
            $order->setStatus($status);
            $order->setPrice($cart->getPrice());
            $entityManager->persist($order);
            $entityManager->flush();
            $this->addFlash('success', 'Your order is received!');
            $session->migrate();
            $this->createCart($entityManager, $session);
        }else {
            $this->addFlash('warning', 'Your cart is empty!');
        }


        return $this->redirectToRoute('cart_index');
    }

    /**
     * @Route("/add/{cart}{id}", name="add_product_to_cart", methods={"GET", "POST"})
     * @param Request $request
     * @param CartRepository $cartRepository
     * @param Product $product
     * @return Response
     */
    public function addProductToCart(Request $request, Cart $cart, Product $product, CartProductRepository $cartProductRepository, SessionInterface $session): Response
    {
        $amount = $request->request->getInt('amount');

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

        if($request->get('route') === 'order'){
            $this->addFlash('success',  $product->getName() .' added to order!');
            return $this->redirectToRoute('order');
        }else{
            $this->addFlash('success',  $product->getName() .' added to cart!');
            return $this->redirectToRoute('product_index');
        }
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

            $cart = $cartRepository->find($cartProduct->getCart()->getId());
            $cart->setPrice($cart->getPrice() - $cartProduct->getProduct()->getPrice()*$cartProduct->getAmount());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($cartProduct);
            $entityManager->persist($cart);
            $entityManager->flush();
        }

        if($request->get('route') === 'order'){
            return $this->redirectToRoute('order');
        }else{
            return $this->redirectToRoute('cart_index');
        }
    }

    /**
     * @Route("/pdf", name="pdf")
     * @param CartProductRepository $cartProductRepository
     * @param Cart $cart
     * @param SessionInterface $session
     */
    public function generate_pdf(CartProductRepository $cartProductRepository, SessionInterface $session)
    {
        $session->start();
        $cart = $session->get('cart');

        if($cart->getPrice() == 0){
            $this->addFlash('warning', 'You need to have some products in cart to view an bill.');
            return $this->redirectToRoute('cart_index');
        }

        $products = $cartProductRepository->findByExampleField($cart->getId());

        $options = new Options();
        $options->set('defaultFont', 'Roboto');


        $dompdf = new Dompdf($options);

        $html = $this->renderView('pdf/pdf.html.twig', [
            'products' => $products,
            'price' => $cart->getPrice()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("bill.pdf", [
            "Attachment" => false
        ]);

    }
}
