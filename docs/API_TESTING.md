# API testing guide

## 1. Enable and prepare the API

Add to `.env`:

```dotenv
AI_CHATBOT_ROUTES_ENABLED=true
AI_CHATBOT_ROUTE_PREFIX=api/ai-chatbot
AI_CHATBOT_RATE_LIMIT_PER_MINUTE=20
AI_CHATBOT_DAILY_MESSAGE_BUDGET=50
AI_CHATBOT_DAILY_TOKEN_BUDGET=50000
AI_CHATBOT_CONVERSATION_TTL_HOURS=168
```

Then run:

```bash
php artisan config:clear
php artisan migrate
php artisan route:list --name=ai-chatbot
```

Set `OPENAI_API_KEY` only for deliberate live testing. Automated tests never make paid requests. Send `Accept: application/json` and `Content-Type: application/json` with JSON requests.

## 2. Read public configuration

```bash
curl -i http://localhost/api/ai-chatbot/config -H "Accept: application/json"
```

Expect `200`. Secrets and internal provider settings are not returned.

## 3. Create an anonymous conversation

```bash
curl -i -X POST http://localhost/api/ai-chatbot/conversations \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"locale":"en","title":"API test"}'
```

Expect `201`. Save `data.id` and `visitor_token`. The token is returned once and persisted only as a secure hash. In Postman, save them as `conversation_id` and `visitor_token` collection variables.

## 4. Send a message

```bash
curl -i -X POST http://localhost/api/ai-chatbot/messages \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-AI-Chatbot-Token: VISITOR_TOKEN" \
  -d '{"conversation_id":"CONVERSATION_ID","idempotency_key":"test-message-001","message":"What public initiatives are available?","locale":"en","sources":["posts"]}'
```

Expect `201` and `duplicate: false`. Repeat the identical request with the same idempotency key; expect `200`, `duplicate: true`, and no additional stored messages.

For Arabic, send a new idempotency key, `"locale":"ar"`, and an Arabic message.

## 5. Retrieve bounded history

```bash
curl -i "http://localhost/api/ai-chatbot/conversations/CONVERSATION_ID/messages?limit=20" \
  -H "Accept: application/json" -H "X-AI-Chatbot-Token: VISITOR_TOKEN"
```

Use optional `before=ISO-8601_TIMESTAMP` for backward pagination. The configured maximum overrides larger limits.

## 6. Verify ownership

Repeat the history request without the token and with a random token. Both must return `404`; the UUID alone never authorizes access.

For authenticated conversations, use the host application's normal authenticated session or token middleware. The package derives ownership from the authenticated user, and another user receives `404`.

## 7. Delete the conversation

```bash
curl -i -X DELETE http://localhost/api/ai-chatbot/conversations/CONVERSATION_ID \
  -H "Accept: application/json" -H "X-AI-Chatbot-Token: VISITOR_TOKEN"
```

Expect `204`; later access returns `404`.

## 8. Expected errors

- Invalid input: `422` with validation errors.
- Missing or incorrect owner credential: `404`.
- Expired conversation: `410` and `conversation_expired`.
- Duplicate request still processing: `409` and `message_processing`.
- Rate or daily budget exceeded: `429`.
- Provider failure: a safe fallback without secrets or raw exception details.

If `web` is included in `ai-chatbot.routes.middleware`, Laravel's normal CSRF middleware applies; send its `X-CSRF-TOKEN`. The package does not enable wildcard CORS. Configure explicit trusted origins in the host when cross-origin browser access is required.

## 9. Run automated tests

From the package directory:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --filter ApiEndpointsTest
vendor/bin/phpunit --configuration phpunit.xml.dist
```

These tests make no live OpenAI requests.
