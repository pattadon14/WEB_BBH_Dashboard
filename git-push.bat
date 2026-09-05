@echo off
title WEB BBH Dashboard - Git Push

echo ================================
echo   WEB BBH Dashboard - Git Push
echo ================================
echo.

git add .

echo.
set /p msg="Enter Note for Edit: "

git commit -m "%msg%"

echo.
echo Uploading on GitHub ...
echo.

git push

echo.
echo ================================
echo   Upload Success
echo ================================
pause