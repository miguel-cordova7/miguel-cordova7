import os
import requests
from datetime import datetime

# Configuración
USERNAME = "miguel-cordova7"
TOKEN = os.getenv("GH_TOKEN")
HEADERS = {"Authorization": f"Bearer {TOKEN}"}

def get_contributions():
    query = """
    query($userName:String!) {
      user(login: $userName){
        contributionsCollection {
          contributionCalendar {
            totalContributions
            weeks {
              contributionDays {
                contributionCount
                date
              }
            }
          }
        }
      }
    }
    """
    variables = {"userName": USERNAME}
    response = requests.post(
        "https://api.github.com/graphql", 
        json={"query": query, "variables": variables}, 
        headers=HEADERS
    )
    if response.status_code != 200:
        raise Exception(f"Error en la API: {response.status_code}")
    return response.json()

def calculate_streaks(data):
    calendar = data['data']['user']['contributionsCollection']['contributionCalendar']
    total = calendar['totalContributions']
    
    days = []
    for week in calendar['weeks']:
        for day in week['contributionDays']:
            days.append(day)
            
    current_streak = 0
    longest_streak = 0
    temp_streak = 0
    
    # Calcular racha más larga
    for day in days:
        if day['contributionCount'] > 0:
            temp_streak += 1
            longest_streak = max(longest_streak, temp_streak)
        else:
            temp_streak = 0
            
    # Calcular racha actual (hacia atrás)
    days_reversed = list(reversed(days))
    for i, day in enumerate(days_reversed):
        if i == 0 and day['contributionCount'] == 0:
            continue
        if day['contributionCount'] > 0:
            current_streak += 1
        else:
            break

    return total, current_streak, longest_streak

def generate_svg(total, current, longest):

    svg_template = f"""
    <svg xmlns="http://www.w3.org/2000/svg" width="495" height="195">
        <rect width="495" height="195" fill="#0d1117" rx="4.5" />
        <text x="50" y="40" fill="#fff" font-family="Segoe UI, Helvetica, Arial, sans-serif" font-weight="bold" font-size="18">Mis Estadísticas</text>
        
        <line x1="165" y1="80" x2="165" y2="160" stroke="#444" stroke-width="1" />
        <line x1="330" y1="80" x2="330" y2="160" stroke="#444" stroke-width="1" />

        <text x="100" y="110" fill="#00a8f3" font-family="sans-serif" font-weight="bold" font-size="28" text-anchor="middle">{total}</text>
        <text x="100" y="140" fill="#fff" font-family="sans-serif" font-size="12" text-anchor="middle">Total</text>

        <text x="247" y="110" fill="#28a745" font-family="sans-serif" font-weight="bold" font-size="28" text-anchor="middle">{current}</text>
        <text x="247" y="140" fill="#fff" font-family="sans-serif" font-size="12" text-anchor="middle">Current Streak</text>

        <text x="395" y="110" fill="#00a8f3" font-family="sans-serif" font-weight="bold" font-size="28" text-anchor="middle">{longest}</text>
        <text x="395" y="140" fill="#fff" font-family="sans-serif" font-size="12" text-anchor="middle">Longest Streak</text>
    </svg>
    """
    with open("racha.svg", "w", encoding="utf-8") as file:
        file.write(svg_template)

if __name__ == "__main__":
    if not TOKEN:
        print("Error: No se encontró el token GH_TOKEN.")
    else:
        print("Obteniendo datos de GitHub...")
        data = get_contributions()
        total, current, longest = calculate_streaks(data)
        generate_svg(total, current, longest)
        print("¡Archivo racha.svg generado con éxito!")
