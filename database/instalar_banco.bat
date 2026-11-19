@echo off
chcp 65001 >nul
REM ============================================================
REM  Cria os bancos fabreck_portal e fabreck_fretes no XAMPP
REM  ATENCAO: apaga e recria os dois bancos (dados de teste)
REM  O MySQL precisa estar iniciado no XAMPP Control Panel
REM ============================================================
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set USUARIO=root
REM Se o root tiver senha, preencha abaixo (padrao do XAMPP: sem senha)
set SENHA=
set AUTH=-u %USUARIO%
if not "%SENHA%"=="" set AUTH=-u %USUARIO% -p%SENHA%
cd /d "%~dp0"

echo.
echo ATENCAO: isto APAGA os bancos fabreck_portal e fabreck_fretes e recria com dados de teste.
echo Fretes, faturas, creditos e usuarios cadastrados neste computador serao perdidos.
set /p CONFIRMA=Para continuar digite APAGAR e tecle Enter:
if /i not "%CONFIRMA%"=="APAGAR" (
  echo Cancelado. Nada foi alterado.
  pause
  exit /b 1
)

echo Recriando bancos...
"%MYSQL%" %AUTH% --default-character-set=utf8mb4 -e "DROP DATABASE IF EXISTS fabreck_fretes; DROP DATABASE IF EXISTS fabreck_portal;" || goto erro

for %%F in (01_portal.sql 02_fretes.sql 03_rotinas.sql 04_carga_planilha.sql 05_usuarios_teste.sql) do (
  echo Importando %%F ...
  "%MYSQL%" %AUTH% --default-character-set=utf8mb4 < %%F || goto erro
)
for %%F in (atualizacoes\*.sql) do (
  echo Importando %%F ...
  "%MYSQL%" %AUTH% --default-character-set=utf8mb4 < "%%F" || goto erro
)

echo.
echo Conferencia - saldo total dos vendedores (esperado 52789.97):
"%MYSQL%" %AUTH% -e "SELECT ROUND(SUM(saldo),2) AS saldo_total FROM fabreck_fretes.vw_saldo_vendedor;"
echo.
echo Pronto!
pause
exit /b 0

:erro
echo.
echo ERRO na importacao. Verifique se o MySQL esta rodando no XAMPP.
pause
exit /b 1
