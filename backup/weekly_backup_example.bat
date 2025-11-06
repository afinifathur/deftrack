@echo off
set DATETIME=%DATE:~10,4%%DATE:~4,2%%DATE:~7,2%_%TIME:~0,2%%TIME:~3,2%%TIME:~6,2%
set DATETIME=%DATETIME: =0%
set DB_HOST=127.0.0.1
set DB_PORT=3306
set DB_USER=root
set DB_PASS=
set DB_NAME=deftrack_db
set OUTDIR=%~dp0
mysqldump --host=%DB_HOST% --port=%DB_PORT% --user=%DB_USER% --password=%DB_PASS% %DB_NAME% > "%OUTDIR%deftrack_%DATETIME%.sql"
echo Backup created at %OUTDIR%
