<?php

namespace AppBundle\Entity;

use AppBundle\BaseTest;
use AppBundle\Entity\Cart\Cart;
use AppBundle\Entity\Cart\CartItem;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Utils\ValidationUtils;
use AppBundle\Validator\Constraints\DeliveryDateInFuture;
use Carbon\Carbon;
use Symfony\Component\Validator\Validation;

class OrderTest extends BaseTest
{
    private $validator;

    public function setUp()
    {
        parent::setUp();

        $this->validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator();

        Carbon::setTestNow(Carbon::create(2017, 9, 2, 11, 0));
    }

    protected function tearDown()
    {
        Carbon::setTestNow();
        parent::tearDown(); // TODO: Change the autogenerated stub
    }

    public function testAddCartItem()
    {
        $order = new Order();
        $cart = new Cart();

        $pizza = new MenuItem();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $salad = new MenuItem();
        $salad
            ->setName('Salad')
            ->setPrice(5);

        $pizzaItem = new CartItem($cart, $pizza, 4);
        $order->addCartItem($pizzaItem, $pizza);

        $saladItem = new CartItem($cart, $salad, 2);
        $order->addCartItem($saladItem, $salad);

        $this->assertCount(2, $order->getOrderedItem());

        $pizzaItem = $order->getOrderedItem()->filter(function (OrderItem $orderItem) use ($pizza) {
            return $orderItem->getMenuItem() === $pizza;
        })->first();

        $saladItem = $order->getOrderedItem()->filter(function (OrderItem $orderItem) use ($salad) {
            return $orderItem->getMenuItem() === $salad;
        })->first();


        $this->assertEquals(4, $pizzaItem->getQuantity());
        $this->assertEquals(2, $saladItem->getQuantity());
    }

    public function testTotal()
    {
        $order = new Order();
        $cart = new Cart();

        $pizza = new MenuItem();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $salad = new MenuItem();
        $salad
            ->setName('Salad')
            ->setPrice(5);

        $pizzaItem = new CartItem($cart, $pizza, 4);
        $order->addCartItem($pizzaItem, $pizza);

        $saladItem = new CartItem($cart, $salad, 2);
        $order->addCartItem($saladItem, $salad);

        $this->assertEquals(50, $order->getTotal());
    }

    public function testTotalWithDelivery()
    {
        $contract = new Contract();
        $contract->setFlatDeliveryPrice(10);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);

        $cart = new Cart();

        $pizza = new MenuItem();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $pizzaItem = new CartItem($cart, $pizza, 4);
        $order->addCartItem($pizzaItem, $pizza);

        $delivery = new Delivery($order);

        $this->assertEquals(50, $order->getTotal());
    }

    public function testDistanceValidation()
    {
        $contract = new Contract();
        $contract->setMinimumCartAmount(0);
        $contract->setFlatDeliveryPrice(10);

        $restaurant = new Restaurant();
        $restaurant->setMaxDistance(3000);
        $restaurant->addOpeningHour('Mo-Sa 11:30-14:30');
        $restaurant->setContract($contract);

        $delivery = new Delivery();

        $order = new Order();
        $order->setDelivery($delivery);
        $order->setRestaurant($restaurant);

        $delivery->setDate(new \DateTime('2017-09-02 12:30:00'));

        // With "Default" group,
        // delivery.distance & delivery.duration are optional
        $errors = $this->validator->validate($order);
        $this->assertEquals(0, count($errors));

        // With "Order" group,
        // delivery.distance & delivery.duration are mandatory
        $errors = $this->validator->validate($order, null, ['order']);

        $this->assertEquals(ValidationUtils::serializeValidationErrors($errors)['delivery.distance'][0], 'This value should not be blank.');

        // Order is valid
        $delivery->setDuration(30);
        $delivery->setDistance(1500);

        $errors = $this->validator->validate($order, null, ['order']);
        $this->assertEquals(0, count($errors));
    }

    public function testDateValidation()
    {
        $contract = new Contract();
        $contract->setMinimumCartAmount(0);
        $contract->setFlatDeliveryPrice(10);

        $restaurant = new Restaurant();
        $restaurant->setMaxDistance(3000);
        $restaurant->addOpeningHour('Mo-Sa 11:30-14:30');
        $restaurant->setContract($contract);

        $delivery = new Delivery();

        $order = new Order();
        $order->setDelivery($delivery);
        $order->setRestaurant($restaurant);

        $delivery->setDuration(30);
        $delivery->setDistance(1500);

        // Restaurant is open
        $delivery->setDate(new \DateTime('2017-09-02 12:30:00'));
        $errors = $this->validator->validate($order, null, ['order']);
        $this->assertEquals(0, count($errors));

        // Restaurant is closed
        $delivery->setDate(new \DateTime('2017-09-03 12:30:00'));
        $errors = $this->validator->validate($order, null, ['order']);
        $this->assertEquals('Restaurant is closed at 2017-09-03 12:30:00', ValidationUtils::serializeValidationErrors($errors)['delivery.date'][0]);

        // Delivery is too soon
        Carbon::setTestNow(Carbon::create(2017, 9, 3, 12, 25));
        $errors = $this->validator->validate($order, null, ['order']);
        $this->assertContains('Delivery date 2017-09-03 12:30:00 is invalid', ValidationUtils::serializeValidationErrors($errors)['delivery.date']);
    }

    public function testMinimumAmountValidation()
    {
        $order = new Order();
        $cart = new Cart();

        $pizza = new MenuItem();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $pizzaItem = new CartItem($cart, $pizza, 1);
        $order->addCartItem($pizzaItem, $pizza);

        $restaurant = new Restaurant();

        $contract = new Contract();
        $contract->setMinimumCartAmount(20);
        $restaurant->setContract($contract);

        $order->setRestaurant($restaurant);

        $errors = $this->validator->validate($order, null, ['order']);
        $this->assertEquals(1, count($errors));
    }

    public function testEventListener()
    {
        $user = $this->createUser('test');
        $this->authenticate($user);

        $foodTaxCategory = $this->createTaxCategory('TVA Restauration', 'tva_restauration', 'TVA 10%', 'tva_10', 0.10, 'float');
        $deliveryTaxCategory = $this->createTaxCategory('TVA livraison', 'tva_livraison', 'TVA 20%', 'tva_20', 0.20, 'float');

        $restaurantAddress = new Address();
        $restaurantAddress->setStreetAddress('XXX');
        $restaurantAddress->setPostalCode('75000');
        $restaurantAddress->setAddressLocality('Paris');

        $restaurant = $this->createRestaurant($restaurantAddress, ['Mo-Su 11:30-14:30'],
            $minimumCartAmount = 05.00, $flatDeliveryPrice = 03.5);

        $delivery = new Delivery();
        $delivery->setDate(new \DateTime('today 12:30:00'));
        $delivery->setDuration(30);
        $delivery->setDistance(1500);

        $order = new Order();
        $order->setDelivery($delivery);
        $order->setRestaurant($restaurant);

        $pizza = $this->createMenuItem('Pizza', 10.00, $foodTaxCategory);

        $cart = new Cart();
        $pizzaItem = new CartItem($cart, $pizza, 1);
        $order->addCartItem($pizzaItem, $pizza);

        $this->doctrine->getManagerForClass(Order::class)->persist($order);
        $this->doctrine->getManagerForClass(Order::class)->flush();

        $this->assertSame($user, $order->getCustomer());
        $this->assertSame($restaurantAddress, $delivery->getOriginAddress());
        $this->assertEquals($flatDeliveryPrice, $delivery->getPrice());
        $this->assertEquals(10.00, $order->getTotalIncludingTax());
        $this->assertEquals(03.50, $delivery->getTotalIncludingTax());
    }

    public function testSetRestaurantUpdatesDelivery()
    {
        $order = new Order();

        $delivery = new Delivery();

        $order->setDelivery($delivery);

        $this->assertNull($delivery->getOriginAddress());
        $this->assertNull($delivery->getPrice());

        $restaurantAddress = new Address();
        $restaurantAddress->setStreetAddress('XXX');
        $restaurantAddress->setPostalCode('75000');
        $restaurantAddress->setAddressLocality('Paris');

        $contract = new Contract();
        $contract->setMinimumCartAmount(20);
        $contract->setFlatDeliveryPrice(3.50);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);
        $restaurant->setAddress($restaurantAddress);

        $order->setRestaurant($restaurant);

        $this->assertSame($restaurantAddress, $delivery->getOriginAddress());
        $this->assertEquals(3.50, $delivery->getPrice());
    }
}
