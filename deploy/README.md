# Deploy scripts for Windows PC

Thu muc nay chua cac lenh mau de copy sang PC Windows.

## Cach dung

1. Cai Git tren PC Windows.
2. Cai GitHub Actions self-hosted runner tren PC Windows cho repo nay.
3. Copy toan bo noi dung thu muc `deploy` nay sang:

```bat
C:\deploy\
```

4. Sua bien `REPO_PATH` trong `C:\deploy\deploy.bat` thanh duong dan code tren PC Windows.
5. Dam bao thu muc code tren PC da clone repo:

```bat
git clone https://github.com/kurotsmile/CarrotHome.git C:\www\CarrotHome
```

6. Khi push len nhanh `main`, GitHub Action se goi:

```bat
C:\deploy\deploy.bat
```

## Luu y

- Workflow chi chay tren PC Windows neu self-hosted runner dang online.
- Script mac dinh dung `git pull --ff-only origin main`. Neu PC co sua code local chua commit, pull co the fail de tranh ghi de code.
- Neu du an can restart service/web server sau khi pull, them lenh vao cuoi `deploy.bat` hoac `pull-latest.ps1`.
