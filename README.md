# ChronosCart – Distributed Flash Sale Engine

**ChronosCart** is a distributed flash sale management system built with Symfony, designed to handle high-traffic sales events while maintaining inventory accuracy and preventing overselling.

The system is designed and load-tested with up to **5,000 simulated purchase requests**, using 100 concurrent workers. It allows multiple users to compete for limited product stock simultaneously. It uses database transaction management, pessimistic locking, Redis caching, and asynchronous message processing to maintain data consistency, optimize performance, and ensure reliable inventory management under heavy traffic.

## Key Features

- **Flash Sale Management** – Create and manage time-limited sales events with configurable discounts and purchase limits.
- **Inventory Management** – Real-time stock tracking and reservation management.
- **Concurrency Control** – Uses MySQL transactions and `SELECT FOR UPDATE` (pessimistic locking) to prevent overselling during simultaneous purchase attempts.
- **Reservation System** – Temporarily reserves products for customers with a 10-minute reservation expiration period.
- **User Purchase Limits** – Restricts purchases per user using identity and IP-based fingerprinting.
- **Dynamic Pricing** – Adjusts product prices based on demand and purchase velocity.
- **Redis Caching** – Improves performance by caching stock information and invalidating cached data when inventory changes.
- **Asynchronous Processing** – Uses Symfony Messenger and Redis to process payment-related tasks in the background.
- **Payment Simulation** – Simulates payment success and failure scenarios, including automatic inventory restocking after failed payments.
- **Order Management** – Tracks order statuses such as Pending, Paid, Failed, and Cancelled.
- **Reservation Expiration** – Automatically handles expired reservations and releases reserved inventory.

## Technology Stack

| Category | Technologies |
|---|---|
| Backend | PHP 8.2, Symfony 7.4 |
| Database | MySQL 8 |
| ORM | Doctrine ORM |
| Caching | Redis |
| Message Queue | Symfony Messenger |
| Frontend | Twig, HTML, CSS, JavaScript, Bootstrap |
| Infrastructure | Docker, Docker Compose |
| Testing | PHPUnit |

## Architecture and Workflow

The main purchase workflow follows these steps:

1. A customer selects a product during an active flash sale.
2. The system validates product availability, sale status, and purchase limits.
3. A database transaction locks the product inventory using pessimistic locking.
4. Available stock is reserved for the customer.
5. The customer proceeds to checkout using a unique reservation token.
6. Payment processing is dispatched asynchronously through Symfony Messenger.
7. If payment succeeds, the order is marked as **PAID** and the reservation is converted.
8. If payment fails, the system releases the reservation and restores the available stock.

## Installation and Setup

### Prerequisites

- Docker Desktop
- Docker Compose
- Git

### 1. Clone the repository

```bash
git clone https://github.com/YOUR_USERNAME/ChronosCart.git
cd ChronosCart
```

### 2. Configure environment variables

Configure the required environment variables in your `.env` or `.env.local` file, including:

- Application environment and secret
- Database connection
- Redis connection
- Messenger transport configuration

### 3. Start Docker containers

```bash
docker compose up -d --build
```

### 4. Install dependencies

```bash
docker exec chronoscart_app composer install
```

### 5. Run database migrations

```bash
docker exec chronoscart_app php bin/console doctrine:migrations:migrate
```

### 6. Start asynchronous message processing

```bash
docker exec -d chronoscart_app php bin/console messenger:consume async --time-limit=3600
```

### 7. Access the application

Open your browser:

http://localhost:8080

## Testing

Run the PHPUnit test suite:

```bash
docker exec chronoscart_app php bin/phpunit
```

The test suite covers important application components, including inventory management, dynamic pricing, reservations, and payment processing.

## Load Testing

ChronosCart includes a custom load-testing script to simulate high-volume concurrent purchase requests.

Example:

```bash
php tests/Support/load_test_requests.php 5000 11
```

Where:

- `5000` – Total number of simulated purchase requests.
- `11` – Flash sale event ID.
- `100` – Configured concurrency level (concurrent workers).

The load test evaluates the application's ability to process high request volumes, manage concurrent inventory reservations, and maintain stock consistency under heavy traffic.

## Project Goals

ChronosCart was developed to demonstrate practical backend engineering concepts, particularly:

- Distributed system principles.
- Database transactions and concurrency management.
- Race condition prevention.
- High-traffic request handling.
- Caching and performance optimization.
- Asynchronous background processing.
- Automated testing and application reliability.

## Application Screenshots

Suggested screenshots:
- Application Homepage
- Product and Flash Sale Management
- Checkout and Order Management
- PHPUnit Test Results
- Load Testing Results


<img width="1895" height="919" alt="front" src="https://github.com/user-attachments/assets/61cc491b-eccd-46f3-9b1b-4207c978806b" />
<img width="1879" height="816" alt="check" src="https://github.com/user-attachments/assets/7f4e48ac-9076-4624-9097-191f70b693b8" />

<img width="606" height="206" alt="testsssss" src="https://github.com/user-attachments/assets/dd4afc8f-3abf-48d9-bcc6-20c0ae804a96" />


---

*ChronosCart – Building reliable flash sales, even under heavy traffic.*
