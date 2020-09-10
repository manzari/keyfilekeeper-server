# Keyfilekeeper
This project is aimed at providing a compromise between two cases:
- disk unencrypted, unattended boot possible
- disk encrypted, unattended boot impossible


Keyfilekeeper exposes an HTTP endpoint, which returns a disk encryption key when requested with the right token.
To prevent replay attacks its using two factor push authentication.

You can have full disk encryption but would need to manually authorize rebooting.

## Develop
```
docker-compose up -d
```
- database runs at 3306
- api runs at 8000