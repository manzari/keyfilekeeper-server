# Keyfilekeeper
This project is aimed at providing a compromise between two cases:
- disk unencrypted, unattended mount possible
- disk encrypted, unattended mount impossible


Keyfilekeeper exposes an HTTP endpoint, which returns a disk encryption key when requested with the right token.
To prevent replay attacks its using two factor push authentication.

You can have disk encryption but need to manually authorize rebooting.

## Develop
```
docker-compose up -d
```
- database runs at 3306
- api runs at 8000