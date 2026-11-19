@echo off
chcp 65001 >nul
REM ============================================================
REM  Aplica as ATUALIZACOES do banco (pasta atualizacoes\) sem apagar dados.
REM  Use este, e nao o instalar_banco.bat, depois que tiver dados reais.
REM  Cada arquivo pode ser rodado mais de uma vez sem problema.
REM ============================================================
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set USUARIO=root
set SENHA=
set AUTH=-u %USUARIO%
if not "%SENHA%"=="" set AUTH=-u %USUARIO% -p%SENHA%
cd /d "%~dp0atualizacoes"

for %%F in (*.sql) do (
  echo Aplicando %%F ...
  "%MYSQL%" %AUTH% --default-character-set=utf8mb4 < "%%F" || goto erro
)
echo.
echo Banco atualizado. Seus dados foram mantidos.
pause
exit /b 0

:erro
echo.
echo ERRO ao aplicar a atualizacao. Verifique se o MySQL esta rodando no XAMPP.
pause
exit /b 1
