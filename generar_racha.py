import os
import requests
from datetime import datetime, timedelta

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
            # Convertir la fecha a un objeto datetime
            day['date_obj'] = datetime.strptime(day['date'], "%Y-%m-%d").date()
            days.append(day)

    # Ordenar por fecha por si acaso
    days.sort(key=lambda x: x['date_obj'])

    current_streak = 0
    current_streak_date = None
    longest_streak = 0
    longest_streak_start = None
    longest_streak_end = None
    temp_streak = 0
    temp_streak_start = None

    today = datetime.now().date()
    yesterday = today - timedelta(days=1)

    # Calcular racha más larga con fechas
    for day in days:
        if day['contributionCount'] > 0:
            if temp_streak == 0:
                temp_streak_start = day['date_obj']
            temp_streak += 1
            if temp_streak > longest_streak:
                longest_streak = temp_streak
                longest_streak_start = temp_streak_start
                longest_streak_end = day['date_obj']
        else:
            temp_streak = 0
            temp_streak_start = None

    # Calcular racha actual (hacia atrás) con fecha
    days_reversed = list(reversed(days))
    for i, day in enumerate(days_reversed):
        if i == 0 and day['contributionCount'] == 0:
            continue
        if day['contributionCount'] > 0:
            if current_streak == 0:
                current_streak_date = day['date_obj']
            current_streak += 1
        else:
            break

    # Obtener fechas formateadas en inglés
    calendar_start_date = days[0]['date_obj']
    current_date_formatted = datetime.now().strftime("%b %d") if current_streak_date else ""
    # Si la racha actual es 0, usar la fecha de ayer o la última con commits
    if current_streak == 0:
        # Buscar el último día con commits para la fecha. Ya tenemos `days_reversed`
        last_commit_day = next((d for d in days_reversed if d['contributionCount'] > 0), None)
        if last_commit_day:
             current_date_formatted = last_commit_day['date_obj'].strftime("%b %d")
        else:
             current_date_formatted = yesterday.strftime("%b %d")

    def format_date_range(start, end):
        if not start or not end:
            return ""
        if start.year == end.year:
             return f"{start.strftime('%b %d')} - {end.strftime('%b %d')}"
        else:
             return f"{start.strftime('%b %d, %Y')} - {end.strftime('%b %d, %Y')}"

    total_date_range = f"{calendar_start_date.strftime('%b %d, %Y')} - Present"
    longest_streak_range = format_date_range(longest_streak_start, longest_streak_end)

    return total, current_streak, longest_streak, total_date_range, current_date_formatted, longest_streak_range

def generate_svg(total, current, longest, total_range, current_date, longest_range):
    svg_template = f"""
    <svg xmlns="http://www.w3.org/2000/svg" width="495" height="195">
        <rect width="495" height="195" fill="#0d1117" rx="4.5" />
        <text x="50" y="40" fill="#fff" font-family="Segoe UI, Helvetica, Arial, sans-serif" font-weight="bold" font-size="18">🌟 Mis Estadísticas</text>

        <line x1="10" y1="55" x2="485" y2="55" stroke="#444" stroke-width="1" />

        <line x1="165" y1="80" x2="165" y2="160" stroke="#444" stroke-width="2.5" stroke-linecap="round" />
        <line x1="330" y1="80" x2="330" y2="160" stroke="#444" stroke-width="2.5" stroke-linecap="round" />

        <text x="100" y="110" fill="#00a8f3" font-family="sans-serif" font-weight="bold" font-size="28" text-anchor="middle">{total}</text>
        <text x="100" y="140" fill="#fff" font-family="sans-serif" font-size="12" text-anchor="middle">Total Contributions</text>
        <text x="100" y="155" fill="#fff" font-family="sans-serif" font-size="10" text-anchor="middle">{total_range}</text>

        <defs>
            <linearGradient id="blue_ring" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#00a8f3;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#02d7f2;stop-opacity:1" />
            </linearGradient>
        </defs>
        <circle cx="247" cy="100" r="35" stroke="url(#blue_ring)" stroke-width="3" fill="none" />

        <text x="247" y="85" fill="#00a8f3" font-family="sans-serif" font-weight="bold" font-size="20" text-anchor="middle">🔥</text>
        
        <text x="247" y="110" fill="#28a745" font-family="sans-serif" font-weight="bold" font-size="28" text-anchor="middle">{current}</text>
        
        <text x="247" y="145" fill="#28a745" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle">Current Streak</text>
        <text x="247" y="160" fill="#fff" font-family="sans-serif" font-size="10" text-anchor="middle">{current_date}</text>

        <text x="395" y="110" fill="#00a8f3" font-family="sans-serif" font-weight="bold" font-size="28" text-anchor="middle">{longest}</text>
        <text x="395" y="140" fill="#fff" font-family="sans-serif" font-size="12" text-anchor="middle">Longest Streak</text>
        <text x="395" y="155" fill="#fff" font-family="sans-serif" font-size="10" text-anchor="middle">{longest_range}</text>
    </svg>
    """
    with open("racha.svg", "w", encoding="utf-8") as file:
        file.write(svg_template)

if __name__ == "__main__":
    if not TOKEN:
        print("Error: No se encontró el token GH_TOKEN.")
    else:
        print("Obteniendo datos de GitHub...")
        try:
             data = get_contributions()
             total, current, longest, total_range, current_date, longest_range = calculate_streaks(data)
             generate_svg(total, current, longest, total_range, current_date, longest_range)
             print("¡Archivo racha.svg generado con éxito!")
        except Exception as e:
             print(f"Ocurrió un error al generar el SVG: {e}")
