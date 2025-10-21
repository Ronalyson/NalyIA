from langchain.document_loaders import DirectoryLoader
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain.embeddings import OllamaEmbeddings
from langchain.vectorstores import Chroma
from langchain.llms import Ollama
from langchain.chains import RetrievalQA

# 1. Carregar os dados da pasta /dados
loader = DirectoryLoader("dados", show_progress=True)
docs = loader.load()

# 2. Dividir os textos em pedaços
splitter = RecursiveCharacterTextSplitter(chunk_size=1000, chunk_overlap=200)
texts = splitter.split_documents(docs)

# 3. Criar embeddings e banco vetorial
embeddings = OllamaEmbeddings(model="llama3")
db = Chroma.from_documents(texts, embeddings)

# 4. Criar o modelo local
llm = Ollama(model="llama3")

# 5. Criar o chatbot com busca contextual
qa = RetrievalQA.from_chain_type(llm=llm, retriever=db.as_retriever())

print("💬 Chat iniciado!")
while True:
    pergunta = input("\nVocê: ")
    if pergunta.lower() in ["sair", "exit", "quit"]:
        break
    resposta = qa.run(pergunta)
    print("IA:", resposta)
