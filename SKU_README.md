# Sistema de Código SKU - Documentação

## O que é SKU?

SKU (Stock Keeping Unit) é um código único que identifica cada produto no sistema. Formato: **SKU-XXXX** (ex: SKU-0001)

## 📋 Como usar o Sistema de SKU

### 1. Encontrar o Código SKU

1. Abra o arquivo **`SKU_CODIGOS.html`** no navegador
2. Procure o produto desejado (está organizado por categorias)
3. Copie o código SKU (ex: SKU-0042)

### 2. Fazer uma Encomenda

1. Aceda ao módulo **Encomendas** no sistema
2. Selecione o **Fornecedor**
3. Na secção "Produtos (use Código SKU)":
   - Cole/digite o código SKU (ex: `SKU-0001`)
   - Digite a **Quantidade** desejada (ex: `5`)
   - Clique em **"+ Adicionar Produto"** se quiser mais itens
4. O total de itens é mostrado automaticamente em baixo
5. Clique em **"Criar Encomenda"**

## 📦 Produtos Disponíveis

Total: **103 produtos** em 10 categorias

### Categorias

| Categoria | SKUs | Exemplos |
|-----------|------|----------|
| 🥤 Bebidas | SKU-0001 a SKU-0022 | Água, Café, Cerveja, Vinho |
| 🧀 Laticinios | SKU-0023 a SKU-0034 | Queijo, Manteiga, Iogurte |
| 🛒 Mercearia | SKU-0035 a SKU-0049 | Arroz, Azeite, Café, Esparguete |
| 🍎 Frutas | SKU-0047 a SKU-0056 | Maçã, Bananas, Uvas, Laranjas |
| 🥕 Legumes | SKU-0057 a SKU-0066 | Alface, Tomate, Cenoura, Brócolis |
| 🍞 Padaria | SKU-0067 a SKU-0071 | Pão, Croissants, Bolos |
| 🥩 Carnes | SKU-0072 a SKU-0077 | Frango, Vaca, Presunto |
| 🐟 Peixe | SKU-0078 a SKU-0083 | Bacalhau, Sardinha, Atum |
| 📦 Enlatados | SKU-0084 a SKU-0091 | Feijão, Atum em lata, Milho |
| 🧴 Higiene | SKU-0092 a SKU-0099 | Sabonete, Champô, Pasta dentes |
| 🧹 Limpeza | SKU-0100 a SKU-0107 | Detergente, Amaciante, Pó |

## ✅ Exemplo Prático

**Quero encomendar:**
- 10 unidades de Água Mineral (SKU-0001)
- 5 unidades de Arroz Carolino (SKU-0002)
- 3 unidades de Azeite (SKU-0003)

**Passos:**
1. Selecciono fornecedor "Luso"
2. Primeiro produto: SKU-0001 → Quantidade: 10
3. Clico "+ Adicionar Produto"
4. Segundo produto: SKU-0002 → Quantidade: 5
5. Clico "+ Adicionar Produto"
6. Terceiro produto: SKU-0003 → Quantidade: 3
7. Total será: 10+5+3 = **18 itens**
8. Clico "Criar Encomenda"

## 🖨️ Imprimir Documento SKU

O ficheiro **SKU_CODIGOS.html** é imprimível:

1. Abra o ficheiro no navegador
2. Pressione **Ctrl+P** (ou Cmd+P no Mac)
3. Clique em "Imprimir"
4. Guarde como referência rápida junto ao balcão

## 📍 Localização dos Ficheiros

- **Encomendas:** Menu → Encomendas
- **Documento SKU:** `/SKU_CODIGOS.html` (na raiz do projeto)
- **Bases de Dados:** Produtos com SKU armazenados em `products.sku`

## 💡 Dicas

✅ SKU ajuda a:
- Aumentar velocidade de encomendas
- Reduzir erros de digitação
- Facilitar procura de produtos
- Permitir encomendas rápidas e precisas

✅ O sistema valida:
- Se o SKU existe na base de dados
- Se a quantidade é válida (≥ 1)
- Se o fornecedor foi selecionado

## 🐛 Resolução de Problemas

**Erro: "SKU 'XXX' não encontrado!"**
- Verifique se o código está correto (espaços extras?)
- Consulte o documento SKU_CODIGOS.html
- A busca é sensível a maiúsculas/minúsculas

**Faltam quantidades?**
- Certifique-se de preencher a quantidade em TODOS os produtos
- A quantidade deve ser ≥ 1

**Fornecedor não selecionado?**
- Selecione um fornecedor antes de enviar
- Nem todos os fornecedores têm todos os produtos

---

**Última atualização:** 15 de janeiro de 2026
**Sistema versão:** 2.0 (com SKU)
