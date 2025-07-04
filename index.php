<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Importador de Bilhetes CSV</title>
</head>
<body>
  <h2>Importar Bilhetes CSV e Gerar XML</h2>
  <form action="process.php" method="post" enctype="multipart/form-data">
    <input type="file" name="csvfile" accept=".csv" required>
    <button type="submit">Enviar</button>
  </form>
</body>
</html>
