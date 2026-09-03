@echo off
title WEB BBH Dashboard - Git Push

echo ================================
echo   WEB BBH Dashboard - Git Push
echo ================================
echo.

git add .

echo.
set /p msg="ใส่ข้อความ Commit: "

git commit -m "%msg%"

echo.
echo กำลัง Upload ขึ้น GitHub...
echo.

git push

echo.
echo ================================
echo   Upload เสร็จเรียบร้อย
echo ================================
pause