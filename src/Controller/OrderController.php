<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Repository\CartProductRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\StatusRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @Route("/order")
 */
class OrderController extends AbstractController
{
    /**
     * @Route("/", name="order")
     */
    public function index(OrderRepository $orderRepository, CartProductRepository $cartProductRepository): Response
    {
        if($this->isGranted('ROLE_ADMIN')){
            $orders = $orderRepository->findAll();
        }else{
            $orders = $orderRepository->findBy(['user' => $this->getUser()]);
        }

        $products = [];
        foreach ($orders as $order)
        {
            $products[] = $cartProductRepository->findByExampleField($order->getCart()->getId());
        }

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'products' => $products
        ]);
    }

    /**
     * @Route("/change/{order}/{direction}", name="status_change", methods={"GET"})
     * @param string $direction
     * @param Order $order
     * @param StatusRepository $statusRepository
     * @return Response
     */
    public function changeStatus( Order $order, string $direction, StatusRepository $statusRepository): Response
    {
        if(!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_EMPLOYEE')){
            $this->denyAccessUnlessGranted($this->getUser());
        }

        $newStatus = $statusRepository->findNext($order->getStatus()->getNumber(), $direction);

        if($newStatus) {
            $order->setStatus($newStatus[0]);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($order);
            $entityManager->flush();
        }

        return $this->redirectToRoute('order');
    }
}
