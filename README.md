# 🧩 Laravel Microservices Starter

A production-ready microservices boilerplate built with **Laravel** and **Docker**. Demonstrates how to decompose a monolith into independent services that communicate via both **REST HTTP** and **RabbitMQ message queues**. Designed as a reference architecture for senior engineers building distributed systems.

---

## 🏗️ Architecture

```
                         ┌─────────────────┐
                         │   API Gateway   │  :8000
                         │  (rate limiting │
                         │   + routing)    │
                         └────────┬────────┘
                                  │ REST proxy
                    ┌─────────────┼─────────────┐
                    │             │             │
             ┌──────▼──────┐ ┌───▼─────────┐   │
             │ User Service│ │Order Service│   │
             │   :8001     │ │   :8002     │   │
             └──────┬──────┘ └──────┬──────┘   │
                    │               │           │
                    └───────┬───────┘           │
                            │ RabbitMQ events   │
                    ┌───────▼────────┐          │
                    │  Notification  │          │
                    │    Service     │          │
                    └────────────────┘          │
                                                │
          ┌──────────┐  ┌────────┐  ┌────────┐  │
          │  MySQL   │  │ Redis  │  │Rabbit  │◄─┘
          │(per svc) │  │(cache) │  │  MQ    │
          └──────────┘  └────────┘  └────────┘
```

---

## 📦 Services

| Service | Responsibility | Port | Communication |
|---|---|---|---|
| **API Gateway** | Route, rate-limit, proxy requests | 8000 | REST (outbound) |
| **User Service** | User CRUD, auth events | 8001 | REST (inbound) + RabbitMQ (publish) |
| **Order Service** | Order management | 8002 | REST (inbound + User Service) + RabbitMQ (publish) |
| **Notification Service** | Email notifications | — | RabbitMQ (consume) |

---

## 🔄 Communication Patterns

### REST (synchronous)
Used when a service needs an **immediate response**:
- API Gateway → User Service (proxy user requests)
- API Gateway → Order Service (proxy order requests)
- Order Service → User Service (verify user exists before creating order)

### RabbitMQ (asynchronous)
Used for **fire-and-forget events** that don't need an immediate response:
- User Service publishes `user.registered` → Notification Service sends welcome email
- Order Service publishes `order.created` → Notification Service sends order confirmation
- Order Service publishes `order.status_changed` → Notification Service sends status update

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Containerisation | Docker + Docker Compose |
| Message Queue | RabbitMQ 3 |
| Cache | Redis 7 |
| Database | MySQL 8 (one DB per service) |
| Mail (dev) | Mailpit (local SMTP + UI) |
| Web Server | Nginx + PHP-FPM |
| Process Manager | Supervisor |

---

## 🚀 Getting Started

### Prerequisites
- Docker Desktop
- Docker Compose v2

### Run everything with one command

```bash
git clone https://github.com/ahmedzarrar42/laravel-microservices-starter.git
cd laravel-microservices-starter

docker compose up -d
```

That's it. Docker will:
1. Start MySQL, Redis, RabbitMQ, and Mailpit
2. Build and start all 4 services
3. Start queue workers for each service

### Available URLs

| Service | URL |
|---|---|
| API Gateway | http://localhost:8000 |
| RabbitMQ Management | http://localhost:15672 (guest/guest) |
| Mailpit (email UI) | http://localhost:8025 |

---

## 📡 API Endpoints (via Gateway)

All requests go through the API Gateway on port 8000.

### Users
```
GET    /api/users
POST   /api/users
GET    /api/users/{id}
PUT    /api/users/{id}
DELETE /api/users/{id}
```

### Orders
```
GET    /api/orders?user_id={id}
POST   /api/orders
GET    /api/orders/{id}
PATCH  /api/orders/{id}/status
```

### Example: Create a user (triggers welcome email via RabbitMQ)
```bash
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -d '{"name":"Muhammad Ahmed","email":"ahmed@example.com","password":"password"}'
```

Then check http://localhost:8025 to see the welcome email arrive.

---

## 🐇 RabbitMQ Event Flow

```
User registers
    → UserController dispatches PublishUserEvent job
    → Job publishes to 'user-events' exchange
    → Notification Service worker consumes message
    → ProcessNotificationEvent handles 'user.registered'
    → Welcome email sent via Mailpit
```

---

## 📐 Design Decisions

**One database per service** — each service owns its data. No shared tables, no cross-service joins. The Order Service stores `user_id` as a plain integer reference, not a foreign key to another DB.

**REST for sync, RabbitMQ for async** — if Service A needs data from Service B to complete its response, use REST. If Service A just needs to notify others that something happened, use RabbitMQ.

**Redis caching in Order Service** — user data fetched from User Service is cached for 5 minutes to reduce inter-service HTTP calls.

**API Gateway rate limiting** — 60 requests per minute per token/IP, enforced at the gateway before requests reach services.

**Supervisor for workers** — each service container runs both PHP-FPM (web) and queue workers via Supervisor, so no separate worker containers are needed in production.

---

## 🔜 Roadmap

- [ ] Service discovery with Consul
- [ ] Distributed tracing with Zipkin
- [ ] JWT validation at the gateway level
- [ ] Health check dashboard
- [ ] Kubernetes deployment manifests

---

## 📄 License

MIT — use this as a reference or starting point for your own microservices architecture.
