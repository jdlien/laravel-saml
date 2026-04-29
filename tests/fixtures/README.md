# Test fixtures

These are throwaway self-signed cert/key pairs used by the test suite.

**They MUST NOT be used in production.** They're committed to the repo so tests
are deterministic; they have a 100-year lifetime and a meaningless CN.

To regenerate (e.g. if a future test needs a fresh pair):

```sh
openssl req -x509 -newkey rsa:2048 \
    -keyout tests/fixtures/test.key \
    -out tests/fixtures/test.crt \
    -days 36500 -nodes \
    -subj "/CN=jdlien-laravel-saml-test"
```
