import json
import gradio as gr
from langchain.document_loaders import DirectoryLoader
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain.embeddings import OllamaEmbeddings
from langchain.vectorstores import Chroma
from langchain.llms import Ollama
from langchain.chains import RetrievalQA

# ======== CONFIGURAÇÕES ==========
with open("config.json", "r", encoding="utf-8") as f:
    cfg = json.load(f)

NOME = cfg["nome"]
EMPRESA = cfg["empresa"]
DESCRICAO = cfg["descricao"]
TOM = cfg["tom"]
MODELO = cfg["modelo"]

# ======== CARREGAR OS DADOS ========
print(f"📂 Carregando dados para {EMPRESA}...")
loader = DirectoryLoader("dados", show_progress=True)
docs = loader.load()

splitter = RecursiveCharacterTextSplitter(chunk_size=1000, chunk_overlap=200)
texts = splitter.split_documents(docs)

# ======== CRIAR EMBEDDINGS E BASE VETORIAL ========
print("⚙️ Criando base vetorial...")
embeddings = OllamaEmbeddings(model=MODELO)
db = Chroma.from_documents(texts, embeddings)

# ======== CONFIGURAR O MODELO LOCAL ========
llm = Ollama(model=MODELO)

# ======== CONFIGURAÇÃO DE PERSONALIDADE ========
system_prompt = f"""
Você é {NOME}, atendente da empresa {EMPRESA}.
Seu papel é realizar o atendimento humano de clientes e funcionários, fornecendo informações sobre produtos, serviços e aplicações de veículos.
Você deve se expressar como um atendente real — natural, educado e prestativo — sem mencionar que é um sistema, robô ou inteligência artificial.

Comportamento esperado:
- Use linguagem humana e espontânea (ex: "Claro, posso te ajudar com isso" ou "Deixa eu confirmar aqui pra você").
- Sempre que possível, informe o nome do produto, referência e aplicação no veículo.
- Se não tiver certeza de uma resposta, diga algo como "vou confirmar essa informação com o setor técnico e te retorno".
- Nunca diga frases como "sou uma IA", "fui treinado", "modelo de linguagem", etc.
- Adapte-se ao nome da empresa informado no arquivo de configuração.
"""

# ======== CHAT RAG ========
qa = RetrievalQA.from_chain_type(
    llm=llm,
    retriever=db.as_retriever(),
    chain_type="stuff",
    chain_type_kwargs={"verbose": False, "prompt": None}
)

# ======== INTERFACE (Gradio) ========
def responder(pergunta):
    prompt = f"{system_prompt}\n\nCliente: {pergunta}\n{NOME}:"
    resposta = qa.run(prompt)
    return resposta.strip()

iface = gr.Interface(
    fn=responder,
    inputs=gr.Textbox(label="Pergunta do cliente"),
    outputs="text",
    title=f"{EMPRESA} — Atendimento ao Cliente",
    description=f"Converse com {NOME}, atendente da {EMPRESA}."
)

print(f"🚀 {NOME} iniciada para {EMPRESA}! Acesse o link abaixo:")
iface.launch()
